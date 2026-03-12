<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'status_details' => $this->status_details,
            'priority' => $this->priority,
            'impact' => $this->impact,
            'urgency' => $this->urgency,
            'source' => $this->source,
            'category_id' => $this->category_id,
            'department_id' => $this->department_id,
            'subcategory' => $this->subcategory,
            'item' => $this->item,
            'requester_id' => $this->requester_id,
            'assigned_to' => $this->assigned_to,
            'tags' => $this->tags,
            'custom_fields' => $this->custom_fields,
            'approval_status' => $this->approval_status,
            'association_type' => $this->association_type,
            'major_incident_type' => $this->major_incident_type,
            'contact_number' => $this->contact_number,
            'requester_location' => $this->requester_location,
            'specific_subject' => $this->specific_subject,
            'customers_impacted' => $this->customers_impacted,
            'impacted_locations' => $this->impacted_locations,
            'planned_effort' => $this->planned_effort,
            'responded_at' => $this->responded_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'planned_start_date' => $this->planned_start_date?->toISOString(),
            'planned_end_date' => $this->planned_end_date?->toISOString(),
            'response_due_at' => $this->response_due_at?->toISOString(),
            'resolution_due_at' => $this->resolution_due_at?->toISOString(),
            'satisfaction_rating' => $this->satisfaction_rating,
            'satisfaction_comment' => $this->satisfaction_comment,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'requester' => new UserResource($this->whenLoaded('requester')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'department' => $this->whenLoaded('department'),
            'sla_policy' => $this->whenLoaded('slaPolicy'),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => $this->whenLoaded('attachments'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
