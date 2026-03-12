<?php

namespace App\Jobs;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Services\Ai\TicketClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClassifyTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 10;

    public function __construct(private Ticket $ticket) {}

    public function handle(TicketClassifier $classifier): void
    {
        try {
            if (!config('ai.api_key')) {
                Log::info('AI classification skipped: no API key configured');
                return;
            }

            $result = $classifier->classify($this->ticket);

            if (!$result['success']) {
                Log::warning('Classification failed', $result);
                return;
            }

            $threshold = config('ai.auto_classify_threshold', 0.70);

            if ($result['confidence'] >= $threshold) {
                $updates = [];

                if ($result['category_id']) {
                    $updates['category_id'] = $result['category_id'];
                }

                if ($result['priority'] && $result['priority'] !== $this->ticket->priority) {
                    $updates['priority'] = $result['priority'];

                    // Update SLA based on new priority
                    $sla = SlaPolicy::withoutGlobalScopes()
                        ->where('tenant_id', $this->ticket->tenant_id)
                        ->where('priority', $result['priority'])
                        ->where('is_active', true)
                        ->first();

                    if ($sla) {
                        $updates['sla_policy_id'] = $sla->id;
                        $updates['response_due_at'] = $this->ticket->created_at->addMinutes($sla->response_time);
                        $updates['resolution_due_at'] = $this->ticket->created_at->addMinutes($sla->resolution_time);
                    }
                }

                if (!empty($updates)) {
                    $this->ticket->update($updates);
                }

                Log::info("Ticket {$this->ticket->ticket_number} auto-classified", [
                    'confidence' => $result['confidence'],
                    'category' => $result['category_name'],
                    'priority' => $result['priority'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Classification job failed', [
                'ticket_id' => $this->ticket->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
