<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Ticket;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationRuleController extends Controller
{
    public function __construct(
        protected AutomationService $automationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rules = AutomationRule::orderBy('execution_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rules->map(fn ($rule) => $this->formatRule($rule)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
            'execution_order' => 'sometimes|integer|min:0',
            'stop_on_match' => 'sometimes|boolean',
            'trigger_event' => 'required|string|in:ticket_created,ticket_updated,ticket_assigned,ticket_closed,ticket_reopened,sla_approaching,sla_breached,comment_added,time_based',
            'conditions' => 'required|array',
            'actions' => 'required|array|min:1',
        ]);

        $rule = AutomationRule::create($validated);

        return response()->json([
            'data' => $this->formatRule($rule),
            'message' => 'Regla de automatización creada',
        ], 201);
    }

    public function show(AutomationRule $automationRule): JsonResponse
    {
        return response()->json([
            'data' => $this->formatRule($automationRule),
        ]);
    }

    public function update(Request $request, AutomationRule $automationRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
            'execution_order' => 'sometimes|integer|min:0',
            'stop_on_match' => 'sometimes|boolean',
            'trigger_event' => 'sometimes|string|in:ticket_created,ticket_updated,ticket_assigned,ticket_closed,ticket_reopened,sla_approaching,sla_breached,comment_added,time_based',
            'conditions' => 'sometimes|array',
            'actions' => 'sometimes|array|min:1',
        ]);

        $automationRule->update($validated);

        return response()->json([
            'data' => $this->formatRule($automationRule),
            'message' => 'Regla de automatización actualizada',
        ]);
    }

    public function destroy(AutomationRule $automationRule): JsonResponse
    {
        $automationRule->delete();

        return response()->json([
            'message' => 'Regla de automatización eliminada',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'required|integer|exists:automation_rules,id',
            'rules.*.execution_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['rules'] as $item) {
            AutomationRule::where('id', $item['id'])->update([
                'execution_order' => $item['execution_order'],
            ]);
        }

        return response()->json([
            'message' => 'Orden de reglas actualizado',
        ]);
    }

    public function toggle(int $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json([
            'data' => $this->formatRule($rule),
            'message' => $rule->is_active ? 'Regla activada' : 'Regla desactivada',
        ]);
    }

    public function test(int $id, int $ticketId): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);
        $ticket = Ticket::findOrFail($ticketId);

        $result = $this->automationService->testRule($rule, $ticket);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function logs(int $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);

        $logs = AutomationLog::where('rule_id', $rule->id)
            ->with('ticket:id,ticket_number,title')
            ->orderByDesc('executed_at')
            ->limit(100)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'ticket_id' => $log->ticket_id,
                'ticket' => $log->ticket ? [
                    'id' => $log->ticket->id,
                    'ticket_number' => $log->ticket->ticket_number,
                    'title' => $log->ticket->title,
                ] : null,
                'trigger_event' => $log->trigger_event,
                'conditions_matched' => $log->conditions_matched,
                'actions_executed' => $log->actions_executed,
                'error' => $log->error,
                'executed_at' => $log->executed_at?->toISOString(),
            ]);

        return response()->json([
            'data' => $logs,
        ]);
    }

    public function availableFields(): JsonResponse
    {
        return response()->json([
            'data' => [
                'condition_fields' => $this->automationService->getAvailableConditionFields(),
                'actions' => $this->automationService->getAvailableActions(),
                'operators' => $this->getOperators(),
                'trigger_events' => $this->getTriggerEvents(),
            ],
        ]);
    }

    public function templates(): JsonResponse
    {
        return response()->json([
            'data' => $this->automationService->getTemplates(),
        ]);
    }

    protected function formatRule(AutomationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'is_active' => $rule->is_active,
            'execution_order' => $rule->execution_order,
            'stop_on_match' => $rule->stop_on_match,
            'trigger_event' => $rule->trigger_event,
            'conditions' => $rule->conditions,
            'actions' => $rule->actions,
            'last_triggered_at' => $rule->last_triggered_at?->toISOString(),
            'trigger_count' => $rule->trigger_count,
            'created_at' => $rule->created_at?->toISOString(),
            'updated_at' => $rule->updated_at?->toISOString(),
        ];
    }

    protected function getOperators(): array
    {
        return [
            ['value' => 'equals', 'label' => 'Es igual a', 'types' => ['text', 'select', 'number', 'boolean']],
            ['value' => 'not_equals', 'label' => 'No es igual a', 'types' => ['text', 'select', 'number', 'boolean']],
            ['value' => 'contains', 'label' => 'Contiene', 'types' => ['text']],
            ['value' => 'not_contains', 'label' => 'No contiene', 'types' => ['text']],
            ['value' => 'greater_than', 'label' => 'Mayor que', 'types' => ['number']],
            ['value' => 'less_than', 'label' => 'Menor que', 'types' => ['number']],
            ['value' => 'in', 'label' => 'Está en', 'types' => ['select']],
            ['value' => 'not_in', 'label' => 'No está en', 'types' => ['select']],
            ['value' => 'is_empty', 'label' => 'Está vacío', 'types' => ['text', 'select', 'number']],
            ['value' => 'is_not_empty', 'label' => 'No está vacío', 'types' => ['text', 'select', 'number']],
            ['value' => 'changed', 'label' => 'Cambió', 'types' => ['text', 'select', 'number']],
            ['value' => 'changed_to', 'label' => 'Cambió a', 'types' => ['text', 'select', 'number']],
        ];
    }

    protected function getTriggerEvents(): array
    {
        return [
            ['value' => 'ticket_created', 'label' => 'Ticket creado'],
            ['value' => 'ticket_updated', 'label' => 'Ticket actualizado'],
            ['value' => 'ticket_assigned', 'label' => 'Ticket asignado'],
            ['value' => 'ticket_closed', 'label' => 'Ticket cerrado'],
            ['value' => 'ticket_reopened', 'label' => 'Ticket reabierto'],
            ['value' => 'sla_approaching', 'label' => 'SLA próximo a vencer'],
            ['value' => 'sla_breached', 'label' => 'SLA incumplido'],
            ['value' => 'comment_added', 'label' => 'Comentario agregado'],
            ['value' => 'time_based', 'label' => 'Basado en tiempo'],
        ];
    }
}
