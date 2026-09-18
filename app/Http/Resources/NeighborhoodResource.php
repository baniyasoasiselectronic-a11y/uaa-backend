<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class NeighborhoodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->when($request->routeIs('*.show'), $this->body),
            'hero_image' => $this->hero_image ? (str_starts_with($this->hero_image, '/') || str_starts_with($this->hero_image, 'http') ? $this->hero_image : Storage::url($this->hero_image)) : null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_featured' => (bool) $this->is_featured,
            'community' => $this->whenLoaded('community', fn () => [
                'id' => $this->community->id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
            ]),
        ];
    }
}
