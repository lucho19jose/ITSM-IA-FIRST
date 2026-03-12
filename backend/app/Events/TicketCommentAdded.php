<?php

namespace App\Events;

use App\Models\TicketComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCommentAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tenantId;

    public function __construct(public TicketComment $comment)
    {
        $this->comment->load('user');
        $this->tenantId = $comment->ticket->tenant_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ticket.{$this->comment->ticket_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'ticket_id' => $this->comment->ticket_id,
            'user_id' => $this->comment->user_id,
            'body' => $this->comment->body,
            'is_internal' => $this->comment->is_internal,
            'user' => $this->comment->user ? [
                'id' => $this->comment->user->id,
                'name' => $this->comment->user->name,
                'avatar_url' => $this->comment->user->avatar_url,
            ] : null,
            'created_at' => $this->comment->created_at->toISOString(),
        ];
    }
}
