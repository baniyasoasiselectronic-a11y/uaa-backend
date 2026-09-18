<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class NewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->when($request->routeIs('*.show'), $this->body),
            'cover_image' => $this->cover_image ? (str_starts_with($this->cover_image, '/') || str_starts_with($this->cover_image, 'http') ? $this->cover_image : Storage::url($this->cover_image)) : null,
            'published_at' => $this->published_at,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
        ];
    }
}
