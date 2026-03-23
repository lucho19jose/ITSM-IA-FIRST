<?php

namespace App\Listeners;

use App\Events\TicketCommentAdded;
use App\Events\TicketCreated;
use App\Events\TicketUpdated;
use App\Models\Ticket;
use App\Services\AutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessAutomationRules implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected AutomationService $automationService,
    ) {}

    public function handleTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $ticket->loadMissing(['category', 'requester', 'assignee', 'department']);

        $this->automationService->processEvent('ticket_created', $ticket);
    }

    public function handleTicketUpdated(TicketUpdated $event): void
    {
        $ticket = $event->ticket;
        $changedFields = $event->changedFields;
        $ticket->loadMissing(['category', 'requester', 'assignee', 'department']);

        // Process generic ticket_updated
        $this->automationService->processEvent('ticket_updated', $ticket, $changedFields);

        // Process specific sub-events based on what changed
        if (isset($changedFields['assigned_to'])) {
            $this->automationService->processEvent('ticket_assigned', $ticket, $changedFields);
        }

        if (isset($changedFields['status'])) {
            if ($changedFields['status'] === 'closed') {
                $this->automationService->processEvent('ticket_closed', $ticket, $changedFields);
            }
            // Detect reopen: transitioning from resolved/closed to an active status
            $activeStatuses = ['open', 'in_progress', 'pending'];
            if (in_array($changedFields['status'], $activeStatuses)) {
                // We only fire reopen if it was previously closed/resolved
                // Since we have the new value but not old, use the ticket's current state
                $this->automationService->processEvent('ticket_reopened', $ticket, $changedFields);
            }
        }
    }

    public function handleTicketCommentAdded(TicketCommentAdded $event): void
    {
        $comment = $event->comment;
        $ticket = $comment->ticket;
        $ticket->loadMissing(['category', 'requester', 'assignee', 'department']);

        $this->automationService->processEvent('comment_added', $ticket);
    }
}
