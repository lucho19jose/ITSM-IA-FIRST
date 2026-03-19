<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketAssociationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'related_ticket_id' => $this->related_ticket_id,
            'type' => $this->type,
            'related_ticket' => [
                'id' => $this->relatedTicket->id,
                'ticket_number' => $this->relatedTicket->ticket_number,
                'title' => $this->relatedTicket->title,
                'status' => $this->relatedTicket->status,
                'priority' => $this->relatedTicket->priority,
            ],
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
