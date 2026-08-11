<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CommunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'hero_image' => $this->hero_image ? Storage::url($this->hero_image) : null,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'amenities' => $this->amenities,
            'is_featured' => (bool) $this->is_featured,
            'properties_count' => $this->whenCounted('properties'),
            'neighborhoods' => NeighborhoodResource::collection($this->whenLoaded('neighborhoods')),
        ];
    }
}
