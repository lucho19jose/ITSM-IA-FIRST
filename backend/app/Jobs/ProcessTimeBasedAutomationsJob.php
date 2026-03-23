<?php

namespace App\Jobs;

use App\Models\AutomationRule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\AutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTimeBasedAutomationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutomationService $automationService): void
    {
        // Process time-based rules per tenant
        $tenantIds = AutomationRule::where('is_active', true)
            ->where('trigger_event', 'time_based')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            try {
                $this->processForTenant($tenantId, $automationService);
            } catch (\Throwable $e) {
                Log::error('Time-based automation failed for tenant', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function processForTenant(int $tenantId, AutomationService $automationService): void
    {
        // Set tenant context
        app()->instance('tenant_id', $tenantId);

        $rules = AutomationRule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('trigger_event', 'time_based')
            ->orderBy('execution_order')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        // Get all open/active tickets for this tenant
        $tickets = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['closed'])
            ->with(['category', 'requester', 'assignee', 'department', 'agentGroup', 'slaPolicy'])
            ->get();

        foreach ($tickets as $ticket) {
            foreach ($rules as $rule) {
                try {
                    $matched = $automationService->evaluateConditions(
                        $rule->conditions ?? [],
                        $ticket,
                        []
                    );

                    if ($matched) {
                        $results = $automationService->executeActions(
                            $rule->actions ?? [],
                            $ticket,
                            $rule
                        );

                        $rule->update([
                            'last_triggered_at' => now(),
                            'trigger_count' => $rule->trigger_count + 1,
                        ]);

                        // Log success
                        \App\Models\AutomationLog::create([
                            'tenant_id' => $tenantId,
                            'rule_id' => $rule->id,
                            'ticket_id' => $ticket->id,
                            'trigger_event' => 'time_based',
                            'conditions_matched' => true,
                            'actions_executed' => $results,
                            'executed_at' => now(),
                        ]);

                        if ($rule->stop_on_match) {
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Time-based automation rule failed', [
                        'rule_id' => $rule->id,
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
