<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'members' => $this->whenLoaded('members', function () {
                return $this->members->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                    'avatar_url' => $m->avatar_url,
                ]);
            }),
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
