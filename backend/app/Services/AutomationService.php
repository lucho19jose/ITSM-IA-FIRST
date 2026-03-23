<?php

namespace App\Services;

use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AutomationService
{
    /**
     * Main entry point — called by event listeners.
     */
    public function processEvent(string $event, Ticket $ticket, array $changedFields = []): void
    {
        $rules = AutomationRule::where('is_active', true)
            ->where('trigger_event', $event)
            ->orderBy('execution_order')
            ->get();

        foreach ($rules as $rule) {
            try {
                $matched = $this->evaluateConditions($rule->conditions ?? [], $ticket, $changedFields);

                if ($matched) {
                    $results = $this->executeActions($rule->actions ?? [], $ticket, $rule);

                    $rule->update([
                        'last_triggered_at' => now(),
                        'trigger_count' => $rule->trigger_count + 1,
                    ]);

                    $this->logExecution($rule, $ticket, $event, true, $results);

                    if ($rule->stop_on_match) {
                        break;
                    }
                } else {
                    $this->logExecution($rule, $ticket, $event, false);
                }
            } catch (\Throwable $e) {
                Log::error('Automation rule execution failed', [
                    'rule_id' => $rule->id,
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);

                $this->logExecution($rule, $ticket, $event, false, null, $e->getMessage());
            }
        }
    }

    /**
     * Dry-run a specific rule against a ticket (for testing).
     */
    public function testRule(AutomationRule $rule, Ticket $ticket): array
    {
        $ticket->loadMissing(['category', 'requester', 'assignee', 'department', 'agentGroup', 'slaPolicy']);

        $conditionsMatched = $this->evaluateConditions($rule->conditions ?? [], $ticket, []);

        $actionsPreview = [];
        if ($conditionsMatched) {
            foreach ($rule->actions ?? [] as $action) {
                $actionsPreview[] = $this->describeAction($action, $ticket);
            }
        }

        return [
            'conditions_matched' => $conditionsMatched,
            'condition_details' => $this->evaluateConditionsDetailed($rule->conditions ?? [], $ticket, []),
            'actions_preview' => $actionsPreview,
        ];
    }

    /**
     * Evaluate condition groups: OR between groups, AND within each group.
     */
    public function evaluateConditions(array $conditionGroups, Ticket $ticket, array $changedFields): bool
    {
        if (empty($conditionGroups)) {
            return true;
        }

        $ticket->loadMissing(['category', 'requester', 'assignee', 'department', 'agentGroup', 'slaPolicy']);

        foreach ($conditionGroups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $groupMatched = true;
            foreach ($group as $condition) {
                if (!$this->evaluateSingleCondition($condition, $ticket, $changedFields)) {
                    $groupMatched = false;
                    break;
                }
            }

            if ($groupMatched) {
                return true; // OR — any group matching is enough
            }
        }

        return false;
    }

    /**
     * Evaluate conditions with details for each condition (for test/dry-run).
     */
    public function evaluateConditionsDetailed(array $conditionGroups, Ticket $ticket, array $changedFields): array
    {
        $details = [];

        foreach ($conditionGroups as $groupIndex => $group) {
            if (!is_array($group)) {
                continue;
            }

            $groupDetails = [];
            foreach ($group as $condition) {
                $matched = $this->evaluateSingleCondition($condition, $ticket, $changedFields);
                $actualValue = $this->resolveFieldValue($condition['field'] ?? '', $ticket);
                $groupDetails[] = [
                    'field' => $condition['field'] ?? '',
                    'operator' => $condition['operator'] ?? '',
                    'expected_value' => $condition['value'] ?? null,
                    'actual_value' => $actualValue,
                    'matched' => $matched,
                ];
            }

            $details[] = [
                'group_index' => $groupIndex,
                'conditions' => $groupDetails,
                'group_matched' => collect($groupDetails)->every('matched'),
            ];
        }

        return $details;
    }

    /**
     * Evaluate a single condition against a ticket.
     */
    protected function evaluateSingleCondition(array $condition, Ticket $ticket, array $changedFields): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $expected = $condition['value'] ?? null;

        // Special operators for change tracking
        if ($operator === 'changed') {
            return in_array($field, array_keys($changedFields));
        }
        if ($operator === 'changed_to') {
            return isset($changedFields[$field]) && (string) $changedFields[$field] === (string) $expected;
        }
        if ($operator === 'changed_from') {
            // changedFields stores new values; we'd need original values
            // For now, check if the field was changed (best effort)
            return in_array($field, array_keys($changedFields));
        }

        $actual = $this->resolveFieldValue($field, $ticket);

        return match ($operator) {
            'equals' => $this->compareEquals($actual, $expected),
            'not_equals' => !$this->compareEquals($actual, $expected),
            'contains' => is_string($actual) && is_string($expected) && str_contains(strtolower($actual), strtolower($expected)),
            'not_contains' => !is_string($actual) || !is_string($expected) || !str_contains(strtolower($actual), strtolower($expected)),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'in' => is_array($expected) && in_array($actual, $expected),
            'not_in' => is_array($expected) && !in_array($actual, $expected),
            'is_empty' => empty($actual),
            'is_not_empty' => !empty($actual),
            default => false,
        };
    }

    /**
     * Resolve a field value from a ticket, supporting dot notation for relations.
     */
    protected function resolveFieldValue(string $field, Ticket $ticket): mixed
    {
        // Time-based computed fields
        if ($field === 'hours_since_created') {
            return $ticket->created_at ? now()->diffInHours($ticket->created_at) : 0;
        }
        if ($field === 'hours_since_updated') {
            return $ticket->updated_at ? now()->diffInHours($ticket->updated_at) : 0;
        }
        if ($field === 'hours_without_response') {
            if ($ticket->responded_at) {
                return 0;
            }
            return $ticket->created_at ? now()->diffInHours($ticket->created_at) : 0;
        }
        if ($field === 'is_assigned') {
            return !empty($ticket->assigned_to);
        }
        if ($field === 'requester_is_vip') {
            return $ticket->requester?->is_vip ?? false;
        }

        // Dot notation for relations: category.name, requester.email, etc.
        if (str_contains($field, '.')) {
            $parts = explode('.', $field, 2);
            $relation = $parts[0];
            $subField = $parts[1];

            $related = $ticket->{$relation};
            if ($related === null) {
                return null;
            }

            return $related->{$subField} ?? null;
        }

        // Direct ticket fields
        return $ticket->{$field} ?? null;
    }

    protected function compareEquals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual)) {
            return $actual === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }
        return (string) $actual === (string) $expected;
    }

    /**
     * Execute all actions for a matched rule.
     */
    public function executeActions(array $actions, Ticket $ticket, AutomationRule $rule): array
    {
        $results = [];

        foreach ($actions as $action) {
            try {
                $result = $this->executeSingleAction($action, $ticket, $rule);
                $results[] = [
                    'type' => $action['type'] ?? 'unknown',
                    'success' => true,
                    'result' => $result,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'type' => $action['type'] ?? 'unknown',
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                Log::warning('Automation action failed', [
                    'rule_id' => $rule->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Execute a single action on a ticket.
     */
    protected function executeSingleAction(array $action, Ticket $ticket, AutomationRule $rule): string
    {
        $type = $action['type'] ?? '';

        return match ($type) {
            'set_field' => $this->actionSetField($action, $ticket),
            'assign_to' => $this->actionAssignTo($action, $ticket),
            'assign_to_group' => $this->actionAssignToGroup($action, $ticket),
            'add_note' => $this->actionAddNote($action, $ticket, $rule),
            'add_tag' => $this->actionAddTag($action, $ticket),
            'remove_tag' => $this->actionRemoveTag($action, $ticket),
            'set_sla_policy' => $this->actionSetSlaPolicy($action, $ticket),
            'send_email' => $this->actionSendEmail($action, $ticket),
            'send_webhook' => $this->actionSendWebhook($action, $ticket, $rule),
            default => "Tipo de acción no reconocido: {$type}",
        };
    }

    protected function actionSetField(array $action, Ticket $ticket): string
    {
        $field = $action['field'] ?? '';
        $value = $action['value'] ?? null;

        $allowedFields = [
            'status', 'priority', 'type', 'urgency', 'impact',
            'category_id', 'department_id', 'status_details',
        ];

        if (!in_array($field, $allowedFields)) {
            return "Campo no permitido: {$field}";
        }

        $updateData = [$field => $value];

        // Track status transitions
        if ($field === 'status') {
            if ($value === 'resolved' && !$ticket->resolved_at) {
                $updateData['resolved_at'] = now();
            }
            if ($value === 'closed' && !$ticket->closed_at) {
                $updateData['closed_at'] = now();
            }
        }

        // Update SLA if priority changed
        if ($field === 'priority' && $value !== $ticket->priority) {
            $sla = SlaPolicy::where('priority', $value)->where('is_active', true)->first();
            if ($sla) {
                $updateData['sla_policy_id'] = $sla->id;
                if (!$ticket->responded_at) {
                    $updateData['response_due_at'] = $ticket->created_at->addMinutes($sla->response_time);
                }
                $updateData['resolution_due_at'] = $ticket->created_at->addMinutes($sla->resolution_time);
            }
        }

        $ticket->update($updateData);

        return "Campo '{$field}' actualizado a '{$value}'";
    }

    protected function actionAssignTo(array $action, Ticket $ticket): string
    {
        $userId = $action['value'] ?? null;

        if ($userId === 'auto') {
            // Auto-assign to least-loaded available agent
            $agent = User::where('role', 'agent')
                ->where('is_active', true)
                ->where('is_available_for_assignment', true)
                ->withCount(['assignedTickets' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed'])])
                ->orderBy('assigned_tickets_count')
                ->first();

            if ($agent) {
                $ticket->update(['assigned_to' => $agent->id]);
                return "Asignado automáticamente a {$agent->name}";
            }
            return 'No se encontró agente disponible para auto-asignación';
        }

        $user = User::find($userId);
        if (!$user) {
            return "Usuario no encontrado: {$userId}";
        }

        $ticket->update(['assigned_to' => $userId]);
        return "Asignado a {$user->name}";
    }

    protected function actionAssignToGroup(array $action, Ticket $ticket): string
    {
        $groupId = $action['value'] ?? null;
        $ticket->update(['agent_group_id' => $groupId]);
        return "Asignado al grupo #{$groupId}";
    }

    protected function actionAddNote(array $action, Ticket $ticket, AutomationRule $rule): string
    {
        $note = $action['value'] ?? '';
        if (empty($note)) {
            return 'Nota vacía, no se agregó';
        }

        // Use the first admin user of the tenant as the note author
        $systemUser = User::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('role', 'admin')
            ->where('is_active', true)
            ->first();

        if (!$systemUser) {
            return 'No se encontró usuario admin para crear la nota';
        }

        $ticket->comments()->create([
            'body' => "[Automatización: {$rule->name}] " . $note,
            'is_internal' => true,
            'user_id' => $systemUser->id,
            'tenant_id' => $ticket->tenant_id,
        ]);

        return 'Nota interna agregada';
    }

    protected function actionAddTag(array $action, Ticket $ticket): string
    {
        $tag = $action['value'] ?? '';
        if (empty($tag)) {
            return 'Etiqueta vacía';
        }

        $tags = $ticket->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $ticket->update(['tags' => $tags]);
        }

        return "Etiqueta '{$tag}' agregada";
    }

    protected function actionRemoveTag(array $action, Ticket $ticket): string
    {
        $tag = $action['value'] ?? '';
        $tags = $ticket->tags ?? [];
        $tags = array_values(array_filter($tags, fn ($t) => $t !== $tag));
        $ticket->update(['tags' => $tags]);

        return "Etiqueta '{$tag}' removida";
    }

    protected function actionSetSlaPolicy(array $action, Ticket $ticket): string
    {
        $policyId = $action['value'] ?? null;
        $policy = SlaPolicy::find($policyId);

        if (!$policy) {
            return "Política SLA no encontrada: {$policyId}";
        }

        $updateData = ['sla_policy_id' => $policy->id];
        if (!$ticket->responded_at) {
            $updateData['response_due_at'] = $ticket->created_at->addMinutes($policy->response_time);
        }
        $updateData['resolution_due_at'] = $ticket->created_at->addMinutes($policy->resolution_time);

        $ticket->update($updateData);

        return "Política SLA establecida: {$policy->name}";
    }

    protected function actionSendEmail(array $action, Ticket $ticket): string
    {
        // Placeholder — integrate with existing mail system
        $to = $action['to'] ?? 'assigned_agent';
        $template = $action['template'] ?? 'generic';

        Log::info('Automation email action triggered', [
            'ticket_id' => $ticket->id,
            'to' => $to,
            'template' => $template,
        ]);

        return "Email programado (to: {$to}, template: {$template})";
    }

    protected function actionSendWebhook(array $action, Ticket $ticket, AutomationRule $rule): string
    {
        $url = $action['url'] ?? '';
        if (empty($url)) {
            return 'URL del webhook vacía';
        }

        // Dispatch as a queued job to avoid blocking
        dispatch(function () use ($url, $ticket, $rule) {
            try {
                \Illuminate\Support\Facades\Http::timeout(10)->post($url, [
                    'event' => 'automation_triggered',
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'ticket' => [
                        'id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'title' => $ticket->title,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                    ],
                    'timestamp' => now()->toISOString(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Automation webhook failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();

        return "Webhook enviado a {$url}";
    }

    /**
     * Describe an action for test/preview purposes.
     */
    protected function describeAction(array $action, Ticket $ticket): array
    {
        $type = $action['type'] ?? 'unknown';

        $description = match ($type) {
            'set_field' => "Establecer campo '{$action['field']}' a '{$action['value']}'",
            'assign_to' => $action['value'] === 'auto' ? 'Auto-asignar al agente menos cargado' : "Asignar al usuario #{$action['value']}",
            'assign_to_group' => "Asignar al grupo #{$action['value']}",
            'add_note' => 'Agregar nota interna: ' . substr($action['value'] ?? '', 0, 100),
            'add_tag' => "Agregar etiqueta '{$action['value']}'",
            'remove_tag' => "Remover etiqueta '{$action['value']}'",
            'set_sla_policy' => "Establecer política SLA #{$action['value']}",
            'send_email' => "Enviar email (to: {$action['to']}, template: {$action['template']})",
            'send_webhook' => "Enviar webhook a {$action['url']}",
            default => "Acción desconocida: {$type}",
        };

        return [
            'type' => $type,
            'description' => $description,
            'config' => $action,
        ];
    }

    /**
     * Log an automation execution.
     */
    protected function logExecution(
        AutomationRule $rule,
        Ticket $ticket,
        string $event,
        bool $conditionsMatched,
        ?array $actionsExecuted = null,
        ?string $error = null,
    ): void {
        AutomationLog::create([
            'tenant_id' => $ticket->tenant_id,
            'rule_id' => $rule->id,
            'ticket_id' => $ticket->id,
            'trigger_event' => $event,
            'conditions_matched' => $conditionsMatched,
            'actions_executed' => $actionsExecuted,
            'error' => $error,
            'executed_at' => now(),
        ]);
    }

    /**
     * Return available condition fields metadata.
     */
    public function getAvailableConditionFields(): array
    {
        return [
            [
                'key' => 'status',
                'label' => 'Estado',
                'type' => 'select',
                'options' => [
                    ['label' => 'Abierto', 'value' => 'open'],
                    ['label' => 'En Progreso', 'value' => 'in_progress'],
                    ['label' => 'Pendiente', 'value' => 'pending'],
                    ['label' => 'Resuelto', 'value' => 'resolved'],
                    ['label' => 'Cerrado', 'value' => 'closed'],
                ],
            ],
            [
                'key' => 'priority',
                'label' => 'Prioridad',
                'type' => 'select',
                'options' => [
                    ['label' => 'Baja', 'value' => 'low'],
                    ['label' => 'Media', 'value' => 'medium'],
                    ['label' => 'Alta', 'value' => 'high'],
                    ['label' => 'Urgente', 'value' => 'urgent'],
                ],
            ],
            [
                'key' => 'type',
                'label' => 'Tipo',
                'type' => 'select',
                'options' => [
                    ['label' => 'Incidente', 'value' => 'incident'],
                    ['label' => 'Solicitud', 'value' => 'request'],
                    ['label' => 'Problema', 'value' => 'problem'],
                    ['label' => 'Cambio', 'value' => 'change'],
                ],
            ],
            [
                'key' => 'impact',
                'label' => 'Impacto',
                'type' => 'select',
                'options' => [
                    ['label' => 'Bajo', 'value' => 'low'],
                    ['label' => 'Medio', 'value' => 'medium'],
                    ['label' => 'Alto', 'value' => 'high'],
                ],
            ],
            [
                'key' => 'urgency',
                'label' => 'Urgencia',
                'type' => 'select',
                'options' => [
                    ['label' => 'Baja', 'value' => 'low'],
                    ['label' => 'Media', 'value' => 'medium'],
                    ['label' => 'Alta', 'value' => 'high'],
                ],
            ],
            [
                'key' => 'source',
                'label' => 'Fuente',
                'type' => 'select',
                'options' => [
                    ['label' => 'Portal', 'value' => 'portal'],
                    ['label' => 'Email', 'value' => 'email'],
                    ['label' => 'Chatbot', 'value' => 'chatbot'],
                    ['label' => 'Catálogo', 'value' => 'catalog'],
                    ['label' => 'API', 'value' => 'api'],
                    ['label' => 'Teléfono', 'value' => 'phone'],
                ],
            ],
            ['key' => 'title', 'label' => 'Título', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Descripción', 'type' => 'text'],
            ['key' => 'assigned_to', 'label' => 'ID del agente asignado', 'type' => 'number'],
            ['key' => 'is_assigned', 'label' => 'Está asignado', 'type' => 'boolean'],
            ['key' => 'category_id', 'label' => 'ID de categoría', 'type' => 'number'],
            ['key' => 'department_id', 'label' => 'ID de departamento', 'type' => 'number'],
            ['key' => 'agent_group_id', 'label' => 'ID de grupo de agentes', 'type' => 'number'],
            ['key' => 'category.name', 'label' => 'Nombre de categoría', 'type' => 'text'],
            ['key' => 'department.name', 'label' => 'Nombre de departamento', 'type' => 'text'],
            ['key' => 'requester.email', 'label' => 'Email del solicitante', 'type' => 'text'],
            ['key' => 'requester.name', 'label' => 'Nombre del solicitante', 'type' => 'text'],
            ['key' => 'requester_is_vip', 'label' => 'Solicitante VIP', 'type' => 'boolean'],
            ['key' => 'assignee.email', 'label' => 'Email del agente', 'type' => 'text'],
            ['key' => 'hours_since_created', 'label' => 'Horas desde creación', 'type' => 'number'],
            ['key' => 'hours_since_updated', 'label' => 'Horas desde actualización', 'type' => 'number'],
            ['key' => 'hours_without_response', 'label' => 'Horas sin respuesta', 'type' => 'number'],
        ];
    }

    /**
     * Return available action types metadata.
     */
    public function getAvailableActions(): array
    {
        return [
            [
                'type' => 'set_field',
                'label' => 'Establecer campo',
                'description' => 'Cambiar el valor de un campo del ticket',
                'config_schema' => [
                    ['key' => 'field', 'label' => 'Campo', 'type' => 'select'],
                    ['key' => 'value', 'label' => 'Valor', 'type' => 'dynamic'],
                ],
            ],
            [
                'type' => 'assign_to',
                'label' => 'Asignar a agente',
                'description' => 'Asignar el ticket a un agente específico o automáticamente',
                'config_schema' => [
                    ['key' => 'value', 'label' => 'Agente', 'type' => 'user_select'],
                ],
            ],
            [
                'type' => 'assign_to_group',
                'label' => 'Asignar a grupo',
                'description' => 'Asignar el ticket a un grupo de agentes',
                'config_schema' => [
                    ['key' => 'value', 'label' => 'Grupo', 'type' => 'group_select'],
                ],
            ],
            [
                'type' => 'add_note',
                'label' => 'Agregar nota interna',
                'description' => 'Agregar una nota interna automática al ticket',
                'config_schema' => [
                    ['key' => 'value', 'label' => 'Nota', 'type' => 'textarea'],
                ],
            ],
            [
                'type' => 'add_tag',
                'label' => 'Agregar etiqueta',
                'description' => 'Agregar una etiqueta al ticket',
                'config_schema' => [
                    ['key' => 'value', 'label' => 'Etiqueta', 'type' => 'text'],
                ],
            ],
            [
                'type' => 'remove_tag',
                'label' => 'Remover etiqueta',
                'description' => 'Remover una etiqueta del ticket',
                'config_schema' => [
                    ['key' => 'value', 'label' => 'Etiqueta', 'type' => 'text'],
                ],
            ],
            [
                'type' => 'set_sla_policy',
                'label' => 'Establecer política SLA',
                'description' => 'Cambiar la política SLA del ticket',
                'config_schema' => [
                    ['key' => 'value', 'label' => 'Política SLA', 'type' => 'sla_select'],
                ],
            ],
            [
                'type' => 'send_email',
                'label' => 'Enviar email',
                'description' => 'Enviar un email de notificación',
                'config_schema' => [
                    ['key' => 'to', 'label' => 'Destinatario', 'type' => 'select', 'options' => [
                        ['label' => 'Agente asignado', 'value' => 'assigned_agent'],
                        ['label' => 'Solicitante', 'value' => 'requester'],
                        ['label' => 'Administradores', 'value' => 'admins'],
                    ]],
                    ['key' => 'template', 'label' => 'Plantilla', 'type' => 'select', 'options' => [
                        ['label' => 'Escalación', 'value' => 'escalation'],
                        ['label' => 'Notificación', 'value' => 'notification'],
                        ['label' => 'Genérico', 'value' => 'generic'],
                    ]],
                ],
            ],
            [
                'type' => 'send_webhook',
                'label' => 'Enviar webhook',
                'description' => 'Enviar datos a una URL externa',
                'config_schema' => [
                    ['key' => 'url', 'label' => 'URL', 'type' => 'url'],
                ],
            ],
        ];
    }

    /**
     * Return pre-built automation templates.
     */
    public function getTemplates(): array
    {
        return [
            [
                'name' => 'Escalar tickets críticos sin asignar después de 1 hora',
                'description' => 'Escala automáticamente tickets urgentes o críticos que no han sido asignados después de 1 hora.',
                'trigger_event' => 'time_based',
                'conditions' => [
                    [
                        ['field' => 'priority', 'operator' => 'in', 'value' => ['urgent', 'high']],
                        ['field' => 'is_assigned', 'operator' => 'equals', 'value' => false],
                        ['field' => 'hours_since_created', 'operator' => 'greater_than', 'value' => 1],
                    ],
                ],
                'actions' => [
                    ['type' => 'set_field', 'field' => 'priority', 'value' => 'urgent'],
                    ['type' => 'add_tag', 'value' => 'escalado'],
                    ['type' => 'add_note', 'value' => 'Escalado automáticamente: ticket de alta prioridad sin asignar después de 1 hora.'],
                ],
                'stop_on_match' => false,
            ],
            [
                'name' => 'Auto-asignar tickets de categoría X al grupo Y',
                'description' => 'Asigna automáticamente los tickets nuevos de una categoría específica a un grupo de agentes.',
                'trigger_event' => 'ticket_created',
                'conditions' => [
                    [
                        ['field' => 'category.name', 'operator' => 'contains', 'value' => 'red'],
                    ],
                ],
                'actions' => [
                    ['type' => 'assign_to_group', 'value' => 1],
                    ['type' => 'add_note', 'value' => 'Asignado automáticamente al grupo por categoría.'],
                ],
                'stop_on_match' => false,
            ],
            [
                'name' => 'Notificar a admin cuando SLA está por vencer',
                'description' => 'Envía notificación a administradores cuando un ticket está próximo a incumplir su SLA.',
                'trigger_event' => 'sla_approaching',
                'conditions' => [
                    [
                        ['field' => 'status', 'operator' => 'not_equals', 'value' => 'resolved'],
                        ['field' => 'status', 'operator' => 'not_equals', 'value' => 'closed'],
                    ],
                ],
                'actions' => [
                    ['type' => 'add_tag', 'value' => 'sla-en-riesgo'],
                    ['type' => 'send_email', 'to' => 'admins', 'template' => 'escalation'],
                    ['type' => 'add_note', 'value' => 'Alerta: SLA próximo a vencer. Se notificó a administradores.'],
                ],
                'stop_on_match' => false,
            ],
            [
                'name' => 'Cerrar tickets resueltos sin actividad por 7 días',
                'description' => 'Cierra automáticamente tickets en estado resuelto que no tienen actividad por 7 días.',
                'trigger_event' => 'time_based',
                'conditions' => [
                    [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'resolved'],
                        ['field' => 'hours_since_updated', 'operator' => 'greater_than', 'value' => 168],
                    ],
                ],
                'actions' => [
                    ['type' => 'set_field', 'field' => 'status', 'value' => 'closed'],
                    ['type' => 'add_note', 'value' => 'Ticket cerrado automáticamente: sin actividad por 7 días después de resolución.'],
                ],
                'stop_on_match' => false,
            ],
            [
                'name' => 'Marcar como urgente tickets de clientes VIP',
                'description' => 'Eleva automáticamente la prioridad de tickets creados por clientes VIP.',
                'trigger_event' => 'ticket_created',
                'conditions' => [
                    [
                        ['field' => 'requester_is_vip', 'operator' => 'equals', 'value' => true],
                        ['field' => 'priority', 'operator' => 'not_equals', 'value' => 'urgent'],
                    ],
                ],
                'actions' => [
                    ['type' => 'set_field', 'field' => 'priority', 'value' => 'urgent'],
                    ['type' => 'add_tag', 'value' => 'vip'],
                    ['type' => 'add_note', 'value' => 'Prioridad elevada automáticamente: solicitante VIP.'],
                ],
                'stop_on_match' => false,
            ],
        ];
    }
}
