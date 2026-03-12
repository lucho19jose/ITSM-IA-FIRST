<?php

namespace App\Services\Ai;

use App\Models\SlaBreach;
use App\Models\Ticket;
use Illuminate\Support\Collection;

class SlaPredictorService
{
    public function checkAtRiskTickets(): Collection
    {
        $atRisk = collect();

        // Check response SLA
        $responseRisk = Ticket::withoutGlobalScopes()
            ->whereNull('responded_at')
            ->whereNotNull('response_due_at')
            ->whereIn('status', ['open'])
            ->where('response_due_at', '<=', now()->addMinutes(30))
            ->get();

        foreach ($responseRisk as $ticket) {
            if ($ticket->response_due_at <= now()) {
                // Already breached
                SlaBreach::withoutGlobalScopes()->firstOrCreate([
                    'tenant_id' => $ticket->tenant_id,
                    'ticket_id' => $ticket->id,
                    'breach_type' => 'response',
                ], [
                    'sla_policy_id' => $ticket->sla_policy_id,
                    'breached_at' => now(),
                ]);
            }
            $atRisk->push([
                'ticket' => $ticket,
                'type' => 'response',
                'minutes_remaining' => now()->diffInMinutes($ticket->response_due_at, false),
            ]);
        }

        // Check resolution SLA
        $resolutionRisk = Ticket::withoutGlobalScopes()
            ->whereNull('resolved_at')
            ->whereNotNull('resolution_due_at')
            ->whereIn('status', ['open', 'in_progress', 'pending'])
            ->where('resolution_due_at', '<=', now()->addHours(2))
            ->get();

        foreach ($resolutionRisk as $ticket) {
            if ($ticket->resolution_due_at <= now()) {
                SlaBreach::withoutGlobalScopes()->firstOrCreate([
                    'tenant_id' => $ticket->tenant_id,
                    'ticket_id' => $ticket->id,
                    'breach_type' => 'resolution',
                ], [
                    'sla_policy_id' => $ticket->sla_policy_id,
                    'breached_at' => now(),
                ]);
            }
            $atRisk->push([
                'ticket' => $ticket,
                'type' => 'resolution',
                'minutes_remaining' => now()->diffInMinutes($ticket->resolution_due_at, false),
            ]);
        }

        return $atRisk;
    }
}
