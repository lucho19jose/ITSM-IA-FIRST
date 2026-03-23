<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Mail\TicketCreatedMail;
use App\Models\NotificationPreference;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketCreatedEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $ticket->loadMissing('requester', 'tenant');

        $requester = $ticket->requester;
        if (!$requester || !$requester->email) {
            return;
        }

        // Check notification preferences
        $prefs = NotificationPreference::getOrCreate($requester->id, $ticket->tenant_id);
        if (!$prefs->wantsEmail('ticket_created')) {
            return;
        }

        try {
            Mail::to($requester->email)->queue(new TicketCreatedMail($ticket));
        } catch (\Throwable $e) {
            Log::error('Failed to send TicketCreatedMail', [
                'ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
