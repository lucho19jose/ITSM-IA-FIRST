<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ],
            'action' => $this->action,
            'subject_type' => class_basename($this->subject_type),
            'subject_id' => $this->subject_id,
            'description' => $this->description,
            'properties' => $this->properties,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
