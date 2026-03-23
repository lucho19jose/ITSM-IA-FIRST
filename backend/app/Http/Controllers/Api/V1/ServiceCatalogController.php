<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceCatalogItem;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $items = ServiceCatalogItem::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string',
            'icon' => 'nullable|string',
            'form_schema' => 'nullable|array',
            'approval_required' => 'boolean',
            'estimated_days' => 'nullable|integer|min:1',
            'sort_order' => 'integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $item = ServiceCatalogItem::create($validated);

        return response()->json(['data' => $item], 201);
    }

    public function show(ServiceCatalogItem $catalogItem): JsonResponse
    {
        return response()->json(['data' => $catalogItem]);
    }

    public function update(Request $request, ServiceCatalogItem $catalogItem): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category' => 'nullable|string',
            'icon' => 'nullable|string',
            'form_schema' => 'nullable|array',
            'is_active' => 'boolean',
            'approval_required' => 'boolean',
            'estimated_days' => 'nullable|integer|min:1',
            'sort_order' => 'integer',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $catalogItem->update($validated);

        return response()->json(['data' => $catalogItem]);
    }

    public function destroy(ServiceCatalogItem $catalogItem): JsonResponse
    {
        $catalogItem->delete();
        return response()->json(['message' => 'Servicio eliminado']);
    }

    public function request(Request $request, ServiceCatalogItem $catalogItem): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
            'form_data' => 'nullable|array',
        ]);

        // If the item requires approval and has a workflow, create an approval instead
        if ($catalogItem->requires_approval && $catalogItem->approval_workflow_id) {
            $workflow = $catalogItem->approvalWorkflow;
            if ($workflow && $workflow->is_active) {
                $approvalService = app(ApprovalService::class);
                $approval = $approvalService->createApproval($catalogItem, $workflow, $request->user());

                return response()->json([
                    'data' => $approval->load(['workflow', 'requester']),
                    'message' => 'Solicitud enviada para aprobación',
                    'requires_approval' => true,
                ], 201);
            }
        }

        $ticketData = [
            'title' => "Solicitud: {$catalogItem->name}",
            'description' => $validated['description'] ?? "Solicitud del catálogo: {$catalogItem->name}",
            'type' => 'request',
            'status' => 'open',
            'priority' => 'medium',
            'source' => Ticket::SOURCE_CATALOG,
            'requester_id' => $request->user()->id,
            'custom_fields' => $validated['form_data'] ?? null,
            'tags' => ['catalog', $catalogItem->slug],
        ];

        // Auto-assign SLA policy
        $sla = SlaPolicy::where('priority', 'medium')->where('is_active', true)->first();
        if ($sla) {
            $ticketData['sla_policy_id'] = $sla->id;
            $ticketData['response_due_at'] = now()->addMinutes($sla->response_time);
            $ticketData['resolution_due_at'] = now()->addMinutes($sla->resolution_time);
        }

        $ticket = Ticket::create($ticketData);

        // Dispatch AI classification
        if (class_exists(\App\Jobs\ClassifyTicketJob::class)) {
            \App\Jobs\ClassifyTicketJob::dispatch($ticket)->onQueue('ai');
        }

        return response()->json(['data' => $ticket], 201);
    }
}
