<?php

namespace App\Jobs;

use App\Mail\SlaBreachWarningMail;
use App\Models\NotificationPreference;
use App\Models\Tenant;
use App\Services\Ai\SlaPredictorService;
use App\Services\WebhookNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckSlaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlaPredictorService $predictor, WebhookNotificationService $webhookService): void
    {
        $atRisk = $predictor->checkAtRiskTickets();

        if ($atRisk->isNotEmpty()) {
            Log::info("SLA Check: {$atRisk->count()} tickets at risk or breached");
        }

        foreach ($atRisk as $item) {
            // Send webhook notification for SLA breach
            $this->sendWebhookNotification($webhookService, $item);

            $ticket = $item['ticket'];
            $breachType = $item['type'];
            $minutesRemaining = $item['minutes_remaining'];

            $ticket->loadMissing('assignee', 'tenant');
            $agent = $ticket->assignee;

            if (!$agent || !$agent->email) {
                continue;
            }

            // Check notification preferences
            $prefs = NotificationPreference::getOrCreate($agent->id, $ticket->tenant_id);
            if (!$prefs->wantsEmail('sla_warning')) {
                continue;
            }

            try {
                Mail::to($agent->email)->queue(
                    new SlaBreachWarningMail($ticket, $agent, $breachType, $minutesRemaining)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send SlaBreachWarningMail', [
                    'ticket_id' => $ticket->id,
                    'agent_id' => $agent->id,
                    'breach_type' => $breachType,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendWebhookNotification(WebhookNotificationService $webhookService, array $item): void
    {
        $ticket = $item['ticket'];
        $minutesRemaining = $item['minutes_remaining'];
        $tenant = Tenant::find($ticket->tenant_id);

        if (!$tenant) return;

        if ($minutesRemaining <= 0) {
            $timeRemaining = 'Vencido';
        } elseif ($minutesRemaining < 60) {
            $timeRemaining = "{$minutesRemaining} min";
        } else {
            $hours = round($minutesRemaining / 60, 1);
            $timeRemaining = "{$hours} horas";
        }

        $domain = $tenant->custom_domain ?: "{$tenant->slug}.autoservice.test";

        $webhookService->notify($tenant, WebhookNotificationService::EVENT_SLA_BREACH, [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'breach_type' => $item['type'] === 'response' ? 'Tiempo de respuesta' : 'Tiempo de resolucion',
            'time_remaining' => $timeRemaining,
            'link' => "https://{$domain}/tickets/{$ticket->id}",
        ]);
    }
}
