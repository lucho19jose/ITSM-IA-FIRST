<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProblemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'impact' => $this->impact,
            'urgency' => $this->urgency,
            'category_id' => $this->category_id,
            'assigned_to' => $this->assigned_to,
            'department_id' => $this->department_id,
            'root_cause' => $this->root_cause,
            'workaround' => $this->workaround,
            'resolution' => $this->resolution,
            'is_known_error' => $this->is_known_error,
            'known_error_id' => $this->known_error_id,
            'related_incidents_count' => $this->related_incidents_count,
            'detected_at' => $this->detected_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'department' => $this->whenLoaded('department'),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
            'known_errors' => KnownErrorResource::collection($this->whenLoaded('knownErrors')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
