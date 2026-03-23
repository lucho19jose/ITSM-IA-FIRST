<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'risk_level' => $this->risk_level,
            'impact' => $this->impact,
            'category_id' => $this->category_id,
            'requested_by' => $this->requested_by,
            'assigned_to' => $this->assigned_to,
            'department_id' => $this->department_id,
            'reason_for_change' => $this->reason_for_change,
            'implementation_plan' => $this->implementation_plan,
            'rollback_plan' => $this->rollback_plan,
            'test_plan' => $this->test_plan,
            'risk_assessment' => $this->risk_assessment ? json_decode($this->risk_assessment, true) : null,
            'scheduled_start' => $this->scheduled_start?->toISOString(),
            'scheduled_end' => $this->scheduled_end?->toISOString(),
            'actual_start' => $this->actual_start?->toISOString(),
            'actual_end' => $this->actual_end?->toISOString(),
            'review_notes' => $this->review_notes,
            'cab_decision' => $this->cab_decision,
            'cab_decided_by' => $this->cab_decided_by,
            'cab_decided_at' => $this->cab_decided_at?->toISOString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'requester' => new UserResource($this->whenLoaded('requester')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'department' => $this->whenLoaded('department'),
            'cab_decider' => new UserResource($this->whenLoaded('cabDecider')),
            'tickets' => $this->whenLoaded('tickets', function () {
                return $this->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'title' => $ticket->title,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'relationship_type' => $ticket->pivot->relationship_type,
                ]);
            }),
            'approvals' => $this->whenLoaded('approvals', function () {
                return $this->approvals->map(fn ($a) => [
                    'id' => $a->id,
                    'approver_id' => $a->approver_id,
                    'approver' => $a->relationLoaded('approver') ? [
                        'id' => $a->approver->id,
                        'name' => $a->approver->name,
                        'email' => $a->approver->email,
                        'avatar_url' => $a->approver->avatar_url,
                    ] : null,
                    'role' => $a->role,
                    'status' => $a->status,
                    'comment' => $a->comment,
                    'decided_at' => $a->decided_at?->toISOString(),
                    'created_at' => $a->created_at?->toISOString(),
                ]);
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
