<?php

namespace App\Listeners;

use App\Events\TicketCommentAdded;
use App\Mail\TicketCommentMail;
use App\Models\NotificationPreference;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketCommentEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TicketCommentAdded $event): void
    {
        $comment = $event->comment;
        $comment->loadMissing('user', 'ticket.requester', 'ticket.assignee', 'ticket.tenant');

        $ticket = $comment->ticket;

        // Skip internal notes — no email for those
        if ($comment->is_internal) {
            return;
        }

        $commenter = $comment->user;
        $requester = $ticket->requester;
        $assignee = $ticket->assignee;

        // Determine recipient:
        // If the commenter is the requester (end user), notify the agent.
        // If the commenter is anyone else (agent/admin), notify the requester.
        if ($commenter && $requester && $commenter->id === $requester->id) {
            // Requester commented → notify assigned agent
            $recipient = $assignee;
        } else {
            // Agent/admin commented → notify requester
            $recipient = $requester;
        }

        if (!$recipient || !$recipient->email) {
            return;
        }

        // Don't send email to the person who wrote the comment
        if ($commenter && $recipient->id === $commenter->id) {
            return;
        }

        // Check notification preferences
        $prefs = NotificationPreference::getOrCreate($recipient->id, $ticket->tenant_id);
        if (!$prefs->wantsEmail('ticket_commented')) {
            return;
        }

        try {
            Mail::to($recipient->email)->queue(new TicketCommentMail($comment, $recipient));
        } catch (\Throwable $e) {
            Log::error('Failed to send TicketCommentMail', [
                'comment_id' => $comment->id,
                'ticket_id' => $ticket->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
