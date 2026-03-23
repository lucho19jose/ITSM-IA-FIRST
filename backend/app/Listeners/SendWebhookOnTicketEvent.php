<?php

namespace App\Listeners;

use App\Events\TicketCommentAdded;
use App\Events\TicketCreated;
use App\Events\TicketUpdated;
use App\Models\Tenant;
use App\Services\WebhookNotificationService;

class SendWebhookOnTicketEvent
{
    public function __construct(protected WebhookNotificationService $service) {}

    public function handleTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $tenant = Tenant::find($ticket->tenant_id);
        if (!$tenant) return;

        $this->service->notify($tenant, WebhookNotificationService::EVENT_TICKET_CREATED, [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'priority' => $ticket->priority,
            'requester' => $ticket->requester?->name ?? 'N/A',
            'type' => $ticket->type,
            'status' => $ticket->status,
            'link' => $this->buildTicketLink($tenant, $ticket->id),
        ]);
    }

    public function handleTicketUpdated(TicketUpdated $event): void
    {
        $ticket = $event->ticket;
        $tenant = Tenant::find($ticket->tenant_id);
        if (!$tenant) return;

        $changedFields = $event->changedFields;

        // Ticket assigned
        if (in_array('assigned_to', $changedFields) && $ticket->assigned_to) {
            $ticket->load('assignee');
            $this->service->notify($tenant, WebhookNotificationService::EVENT_TICKET_ASSIGNED, [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'priority' => $ticket->priority,
                'assignee' => $ticket->assignee?->name ?? 'N/A',
                'link' => $this->buildTicketLink($tenant, $ticket->id),
            ]);
        }

        // Ticket closed
        if (in_array('status', $changedFields) && in_array($ticket->status, ['closed', 'resolved'])) {
            $resolutionTime = 'N/A';
            if ($ticket->created_at && $ticket->resolved_at) {
                $diffMinutes = $ticket->created_at->diffInMinutes($ticket->resolved_at);
                if ($diffMinutes < 60) {
                    $resolutionTime = "{$diffMinutes} min";
                } elseif ($diffMinutes < 1440) {
                    $hours = round($diffMinutes / 60, 1);
                    $resolutionTime = "{$hours} horas";
                } else {
                    $days = round($diffMinutes / 1440, 1);
                    $resolutionTime = "{$days} dias";
                }
            }

            $this->service->notify($tenant, WebhookNotificationService::EVENT_TICKET_CLOSED, [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'resolution_time' => $resolutionTime,
                'link' => $this->buildTicketLink($tenant, $ticket->id),
            ]);
        }
    }

    public function handleTicketCommentAdded(TicketCommentAdded $event): void
    {
        $comment = $event->comment;
        $ticket = $comment->ticket;
        $tenant = Tenant::find($ticket->tenant_id);
        if (!$tenant) return;

        // Don't notify for internal notes
        if ($comment->is_internal) return;

        $this->service->notify($tenant, WebhookNotificationService::EVENT_TICKET_COMMENTED, [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'commenter' => $comment->user?->name ?? 'N/A',
            'comment_body' => $comment->body,
            'link' => $this->buildTicketLink($tenant, $ticket->id),
        ]);
    }

    private function buildTicketLink(Tenant $tenant, int $ticketId): string
    {
        $domain = $tenant->custom_domain ?: "{$tenant->slug}.autoservice.test";
        return "https://{$domain}/tickets/{$ticketId}";
    }
}
