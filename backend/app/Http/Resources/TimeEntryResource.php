<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ],
            'hours' => (float) $this->hours,
            'note' => $this->note,
            'executed_at' => $this->executed_at->toDateString(),
            'billable' => $this->billable,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
