<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public array $changedFields = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->ticket->tenant_id}"),
            new PrivateChannel("ticket.{$this->ticket->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        $this->ticket->load('assignee', 'category');

        return [
            'id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title' => $this->ticket->title,
            'status' => $this->ticket->status,
            'priority' => $this->ticket->priority,
            'type' => $this->ticket->type,
            'assigned_to' => $this->ticket->assigned_to,
            'assignee_name' => $this->ticket->assignee?->name,
            'category' => $this->ticket->category?->name,
            'changed_fields' => $this->changedFields,
            'updated_at' => $this->ticket->updated_at->toISOString(),
        ];
    }
}
