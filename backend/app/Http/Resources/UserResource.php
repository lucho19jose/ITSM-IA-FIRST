<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'is_vip' => $this->is_vip,
            'department_id' => $this->department_id,
            'phone' => $this->phone,
            'work_phone' => $this->work_phone,
            'location' => $this->location,
            'address' => $this->address,
            'job_title' => $this->job_title,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatar_url,
            'signature' => $this->signature,
            'is_available_for_assignment' => $this->is_available_for_assignment,
            'time_format' => $this->time_format,
            'tenant_id' => $this->tenant_id,
            'department' => $this->whenLoaded('department'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
