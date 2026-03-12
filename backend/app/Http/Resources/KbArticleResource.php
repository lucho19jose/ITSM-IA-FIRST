<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KbArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'status' => $this->status,
            'author_id' => $this->author_id,
            'views_count' => $this->views_count,
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,
            'is_public' => $this->is_public,
            'published_at' => $this->published_at?->toISOString(),
            'category' => $this->whenLoaded('category'),
            'author' => new UserResource($this->whenLoaded('author')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
