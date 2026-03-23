<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SavedReport;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    /**
     * List saved reports (own + shared).
     */
    public function index(Request $request): JsonResponse
    {
        $reports = SavedReport::where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('is_shared', true);
            })
            ->with('user:id,name')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $reports]);
    }

    /**
     * Store a new saved report.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'report_type' => 'required|in:tickets,agents,sla,categories,trends,custom',
            'config' => 'required|array',
            'config.entity' => 'required|string|in:tickets,agents,categories',
            'config.filters' => 'nullable|array',
            'config.group_by' => 'nullable|string',
            'config.metrics' => 'required|array|min:1',
            'config.date_range' => 'nullable|array',
            'config.chart_type' => 'nullable|string|in:bar,line,pie,table',
            'config.columns' => 'nullable|array',
            'is_shared' => 'sometimes|boolean',
            'schedule_cron' => 'nullable|string|max:100',
            'schedule_emails' => 'nullable|array',
            'schedule_emails.*' => 'email',
        ]);

        // Only admin can share reports
        if (!$request->user()->isAdmin()) {
            $validated['is_shared'] = false;
        }

        $validated['user_id'] = $request->user()->id;

        $report = SavedReport::create($validated);
        $report->load('user:id,name');

        return response()->json(['data' => $report], 201);
    }

    /**
     * Show a single saved report config.
     */
    public function show(Request $request, SavedReport $report): JsonResponse
    {
        // Check access: own or shared
        if ($report->user_id !== $request->user()->id && !$report->is_shared && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $report->load('user:id,name');

        return response()->json(['data' => $report]);
    }

    /**
     * Update a saved report.
     */
    public function update(Request $request, SavedReport $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string|max:1000',
            'report_type' => 'sometimes|in:tickets,agents,sla,categories,trends,custom',
            'config' => 'sometimes|array',
            'config.entity' => 'sometimes|string|in:tickets,agents,categories',
            'config.filters' => 'nullable|array',
            'config.group_by' => 'nullable|string',
            'config.metrics' => 'sometimes|array|min:1',
            'config.date_range' => 'nullable|array',
            'config.chart_type' => 'nullable|string|in:bar,line,pie,table',
            'config.columns' => 'nullable|array',
            'is_shared' => 'sometimes|boolean',
            'schedule_cron' => 'nullable|string|max:100',
            'schedule_emails' => 'nullable|array',
            'schedule_emails.*' => 'email',
        ]);

        if (!$request->user()->isAdmin()) {
            unset($validated['is_shared']);
        }

        $report->update($validated);
        $report->load('user:id,name');

        return response()->json(['data' => $report]);
    }

    /**
     * Delete a saved report.
     */
    public function destroy(Request $request, SavedReport $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $report->delete();

        return response()->json(null, 204);
    }

    /**
     * Execute a saved report and return data.
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $report = SavedReport::findOrFail($id);

        if ($report->user_id !== $request->user()->id && !$report->is_shared && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $result = $this->reportService->executeReport($report);

        $report->update(['last_run_at' => now()]);

        return response()->json(['data' => $result]);
    }

    /**
     * Preview an unsaved report config.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity' => 'required|string|in:tickets,agents,categories',
            'filters' => 'nullable|array',
            'group_by' => 'nullable|string',
            'metrics' => 'required|array|min:1',
            'date_range' => 'nullable|array',
            'chart_type' => 'nullable|string|in:bar,line,pie,table',
            'columns' => 'nullable|array',
        ]);

        $result = $this->reportService->executeConfig($validated);

        return response()->json(['data' => $result]);
    }

    /**
     * Export a saved report as CSV download.
     */
    public function export(Request $request, int $id): StreamedResponse
    {
        $report = SavedReport::findOrFail($id);

        if ($report->user_id !== $request->user()->id && !$report->is_shared && !$request->user()->isAdmin()) {
            abort(403, 'No autorizado');
        }

        $result = $this->reportService->executeReport($report);
        $columns = $report->config['columns'] ?? ['group_label', 'count'];
        $csv = $this->reportService->exportToCsv($result['data'], $columns);

        $report->update(['last_run_at' => now()]);

        $filename = str_replace(' ', '_', $report->name) . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Return list of pre-built report templates.
     */
    public function templates(): JsonResponse
    {
        return response()->json([
            'data' => $this->reportService->getPrebuiltReports(),
        ]);
    }

    /**
     * Return available fields (filters, metrics, groupings) per entity.
     */
    public function availableFields(Request $request): JsonResponse
    {
        $entity = $request->query('entity', 'tickets');

        return response()->json([
            'data' => [
                'filters' => $this->reportService->getAvailableFilters($entity),
                'metrics' => $this->reportService->getAvailableMetrics($entity),
                'groupings' => $this->reportService->getAvailableGroupings($entity),
            ],
        ]);
    }
}
