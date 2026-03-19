<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScenarioResource;
use App\Models\Scenario;
use App\Models\Ticket;
use App\Models\SlaPolicy;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScenarioController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ScenarioResource::collection(
            Scenario::where('is_active', true)->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'actions' => 'required|array|min:1',
            'actions.*.field' => 'required|string',
            'actions.*.value' => 'present',
        ]);

        $scenario = Scenario::create($validated);

        return response()->json([
            'data' => new ScenarioResource($scenario),
        ], 201);
    }

    public function update(Request $request, Scenario $scenario): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'actions' => 'sometimes|array|min:1',
            'actions.*.field' => 'required_with:actions|string',
            'actions.*.value' => 'required_with:actions|present',
            'is_active' => 'sometimes|boolean',
        ]);

        $scenario->update($validated);

        return response()->json([
            'data' => new ScenarioResource($scenario),
        ]);
    }

    public function destroy(Scenario $scenario): JsonResponse
    {
        $scenario->delete();
        return response()->json(['message' => 'Escenario eliminado']);
    }

    public function execute(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'scenario_id' => 'required|integer|exists:scenarios,id',
        ]);

        $scenario = Scenario::findOrFail($validated['scenario_id']);
        $updateData = [];
        $addNote = null;

        foreach ($scenario->actions as $action) {
            $field = $action['field'];
            $value = $action['value'];

            if ($field === 'add_note') {
                $addNote = $value;
                continue;
            }

            if (in_array($field, ['status', 'priority', 'type', 'urgency', 'impact', 'assigned_to', 'agent_group_id', 'category_id', 'department_id'])) {
                $updateData[$field] = $value;
            }
        }

        // Track status transitions
        if (isset($updateData['status'])) {
            if ($updateData['status'] === 'resolved' && !$ticket->resolved_at) {
                $updateData['resolved_at'] = now();
            }
            if ($updateData['status'] === 'closed' && !$ticket->closed_at) {
                $updateData['closed_at'] = now();
            }
        }

        // Update SLA if priority changed
        if (isset($updateData['priority']) && $updateData['priority'] !== $ticket->priority) {
            $sla = SlaPolicy::where('priority', $updateData['priority'])->where('is_active', true)->first();
            if ($sla) {
                $updateData['sla_policy_id'] = $sla->id;
                if (!$ticket->responded_at) {
                    $updateData['response_due_at'] = $ticket->created_at->addMinutes($sla->response_time);
                }
                $updateData['resolution_due_at'] = $ticket->created_at->addMinutes($sla->resolution_time);
            }
        }

        if (!empty($updateData)) {
            $ticket->update($updateData);
        }

        // Add note if specified
        if ($addNote) {
            $ticket->comments()->create([
                'body' => $addNote,
                'is_internal' => true,
                'user_id' => $request->user()->id,
                'tenant_id' => app('tenant_id'),
            ]);
        }

        ActivityLogService::log(
            $request->user(), 'scenario_executed', $ticket,
            "ejecutó el escenario \"{$scenario->name}\" en el ticket {$ticket->title} ({$ticket->ticket_number})",
            [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_title' => $ticket->title,
                'scenario_id' => $scenario->id,
                'scenario_name' => $scenario->name,
            ]
        );

        return response()->json([
            'data' => new \App\Http\Resources\TicketResource($ticket->fresh()->load(['category', 'requester', 'assignee', 'department'])),
            'message' => "Escenario \"{$scenario->name}\" ejecutado",
        ]);
    }
}
