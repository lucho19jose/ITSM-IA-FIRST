<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
        $this->ticket->load('requester', 'category');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->ticket->tenant_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title' => $this->ticket->title,
            'status' => $this->ticket->status,
            'priority' => $this->ticket->priority,
            'type' => $this->ticket->type,
            'source' => $this->ticket->source,
            'requester' => $this->ticket->requester ? [
                'id' => $this->ticket->requester->id,
                'name' => $this->ticket->requester->name,
            ] : null,
            'category' => $this->ticket->category?->name,
            'assigned_to' => $this->ticket->assigned_to,
            'created_at' => $this->ticket->created_at->toISOString(),
        ];
    }
}
