<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnownErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'problem_id' => $this->problem_id,
            'title' => $this->title,
            'description' => $this->description,
            'workaround' => $this->workaround,
            'root_cause' => $this->root_cause,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'problem' => new ProblemResource($this->whenLoaded('problem')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
