<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PropertyListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'reference_code' => $this->reference_code,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'price' => $this->price,
            'currency' => $this->currency,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'area_sqft' => $this->area_sqft,
            'address' => $this->address,
            'main_image' => $this->main_image ? (str_starts_with($this->main_image, '/') || str_starts_with($this->main_image, 'http') ? $this->main_image : Storage::url($this->main_image)) : null,
            'is_featured' => (bool) $this->is_featured,
            'community' => $this->whenLoaded('community', fn () => [
                'id' => $this->community->id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
            ]),
            'type' => $this->whenLoaded('propertyType', fn () => [
                'id' => $this->propertyType->id,
                'name' => $this->propertyType->name,
                'slug' => $this->propertyType->slug,
            ]),
            'amenities' => AmenityResource::collection($this->whenLoaded('amenities')),
        ];
    }
}
