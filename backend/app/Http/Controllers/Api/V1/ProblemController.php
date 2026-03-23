<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProblemResource;
use App\Http\Resources\KnownErrorResource;
use App\Models\KnownError;
use App\Models\Problem;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProblemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Problem::with(['category', 'assignee', 'department']);

        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }
        if ($request->filled('priority')) {
            $priorities = explode(',', $request->priority);
            $query->whereIn('priority', $priorities);
        }
        if ($request->filled('impact')) {
            $query->where('impact', $request->impact);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('is_known_error')) {
            $query->where('is_known_error', $request->boolean('is_known_error'));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('root_cause', 'like', "%{$search}%");
            });
        }
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        return ProblemResource::collection($query->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'impact' => 'sometimes|in:low,medium,high,extensive',
            'urgency' => 'sometimes|in:low,medium,high,critical',
            'category_id' => 'nullable|exists:categories,id',
            'assigned_to' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'detected_at' => 'nullable|date',
            'ticket_ids' => 'nullable|array',
            'ticket_ids.*' => 'integer|exists:tickets,id',
        ]);

        $ticketIds = $validated['ticket_ids'] ?? [];
        unset($validated['ticket_ids']);

        $validated['status'] = 'logged';

        $problem = Problem::create($validated);

        if (!empty($ticketIds)) {
            $problem->tickets()->attach($ticketIds);
            $problem->update(['related_incidents_count' => count($ticketIds)]);
        }

        ActivityLogService::log(
            $request->user(), 'created', $problem,
            "creó el problema: {$problem->title}",
            ['problem_id' => $problem->id, 'problem_title' => $problem->title]
        );

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department', 'tickets'])),
        ], 201);
    }

    public function show(Problem $problem): JsonResponse
    {
        $problem->load(['category', 'assignee', 'department', 'tickets.requester', 'tickets.assignee', 'knownErrors']);

        return response()->json([
            'data' => new ProblemResource($problem),
        ]);
    }

    public function update(Request $request, Problem $problem): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:logged,categorized,investigating,root_cause_identified,known_error,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'impact' => 'sometimes|in:low,medium,high,extensive',
            'urgency' => 'sometimes|in:low,medium,high,critical',
            'category_id' => 'nullable|exists:categories,id',
            'assigned_to' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'root_cause' => 'nullable|string',
            'workaround' => 'nullable|string',
            'resolution' => 'nullable|string',
            'detected_at' => 'nullable|date',
        ]);

        // Track status transitions
        if (isset($validated['status'])) {
            if ($validated['status'] === 'resolved' && !$problem->resolved_at) {
                $validated['resolved_at'] = now();
            }
            if ($validated['status'] === 'closed' && !$problem->closed_at) {
                $validated['closed_at'] = now();
            }
            if ($validated['status'] === 'known_error') {
                $validated['is_known_error'] = true;
            }
        }

        $oldStatus = $problem->status;
        $problem->update($validated);

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            ActivityLogService::log(
                $request->user(), 'updated', $problem,
                "cambió el estado del problema {$problem->title} de {$oldStatus} a {$validated['status']}",
                ['problem_id' => $problem->id, 'problem_title' => $problem->title, 'field' => 'status', 'old_value' => $oldStatus, 'new_value' => $validated['status']]
            );
        }

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department'])),
        ]);
    }

    public function destroy(Problem $problem): JsonResponse
    {
        $problem->delete();
        return response()->json(['message' => 'Problema eliminado'], 200);
    }

    public function linkTickets(Request $request, int $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'integer|exists:tickets,id',
        ]);

        $problem->tickets()->syncWithoutDetaching($validated['ticket_ids']);
        $problem->refreshIncidentsCount();

        ActivityLogService::log(
            $request->user(), 'updated', $problem,
            "vinculó tickets al problema {$problem->title}",
            ['problem_id' => $problem->id, 'problem_title' => $problem->title, 'ticket_ids' => $validated['ticket_ids']]
        );

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department', 'tickets'])),
            'message' => 'Tickets vinculados exitosamente',
        ]);
    }

    public function unlinkTicket(Request $request, int $id, int $ticketId): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        $problem->tickets()->detach($ticketId);
        $problem->refreshIncidentsCount();

        ActivityLogService::log(
            $request->user(), 'updated', $problem,
            "desvinculó el ticket #{$ticketId} del problema {$problem->title}",
            ['problem_id' => $problem->id, 'problem_title' => $problem->title, 'ticket_id' => $ticketId]
        );

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department', 'tickets'])),
            'message' => 'Ticket desvinculado exitosamente',
        ]);
    }

    public function promoteToKnownError(Request $request, int $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        if (!$problem->root_cause && !$problem->workaround) {
            return response()->json([
                'message' => 'El problema debe tener una causa raíz o workaround antes de promoverlo a error conocido',
            ], 422);
        }

        $knownError = KnownError::create([
            'problem_id' => $problem->id,
            'title' => $problem->title,
            'description' => $problem->description,
            'workaround' => $problem->workaround,
            'root_cause' => $problem->root_cause,
            'status' => 'open',
            'category_id' => $problem->category_id,
        ]);

        $problem->update([
            'is_known_error' => true,
            'known_error_id' => 'KE-' . str_pad($knownError->id, 5, '0', STR_PAD_LEFT),
            'status' => 'known_error',
        ]);

        ActivityLogService::log(
            $request->user(), 'updated', $problem,
            "promovió el problema {$problem->title} a error conocido",
            ['problem_id' => $problem->id, 'problem_title' => $problem->title, 'known_error_id' => $knownError->id]
        );

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department', 'knownErrors'])),
            'known_error' => new KnownErrorResource($knownError),
            'message' => 'Error conocido creado exitosamente',
        ]);
    }

    public function updateRootCause(Request $request, int $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        $validated = $request->validate([
            'root_cause' => 'required|string',
            'workaround' => 'nullable|string',
        ]);

        $problem->update([
            'root_cause' => $validated['root_cause'],
            'workaround' => $validated['workaround'] ?? $problem->workaround,
            'status' => in_array($problem->status, ['logged', 'categorized', 'investigating'])
                ? 'root_cause_identified'
                : $problem->status,
        ]);

        ActivityLogService::log(
            $request->user(), 'updated', $problem,
            "actualizó la causa raíz del problema {$problem->title}",
            ['problem_id' => $problem->id, 'problem_title' => $problem->title]
        );

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department'])),
            'message' => 'Causa raíz actualizada',
        ]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        $validated = $request->validate([
            'resolution' => 'required|string',
        ]);

        $problem->update([
            'resolution' => $validated['resolution'],
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        ActivityLogService::log(
            $request->user(), 'updated', $problem,
            "resolvió el problema {$problem->title}",
            ['problem_id' => $problem->id, 'problem_title' => $problem->title]
        );

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department'])),
            'message' => 'Problema resuelto',
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $problem = Problem::findOrFail($id);

        $problem->update([
            'status' => 'closed',
            'closed_at' => now(),
            'resolved_at' => $problem->resolved_at ?? now(),
        ]);

        ActivityLogService::log(
            $request->user(), 'closed', $problem,
            "cerró el problema {$problem->title}",
            ['problem_id' => $problem->id, 'problem_title' => $problem->title]
        );

        return response()->json([
            'data' => new ProblemResource($problem->load(['category', 'assignee', 'department'])),
            'message' => 'Problema cerrado',
        ]);
    }
}
