<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PropertyResource extends JsonResource
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
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'area_sqft' => $this->area_sqft,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'main_image' => $this->main_image ? Storage::url($this->main_image) : null,
            'video_url' => $this->video_url,
            'virtual_tour_url' => $this->virtual_tour_url,
            'floor_plan' => $this->floor_plan ? Storage::url($this->floor_plan) : null,
            'is_featured' => (bool) $this->is_featured,
            'views' => $this->views,
            'published_at' => $this->published_at,
            'community' => $this->whenLoaded('community', fn () => [
                'id' => $this->community->id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
                'location' => $this->community->location,
            ]),
            'type' => $this->whenLoaded('propertyType', fn () => [
                'id' => $this->propertyType->id,
                'name' => $this->propertyType->name,
                'slug' => $this->propertyType->slug,
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => Storage::url($img->path),
                'alt' => $img->alt,
                'is_main' => (bool) $img->is_main,
            ])),
            'videos' => $this->whenLoaded('videos', fn () => $this->videos->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'url' => $v->url ?: ($v->path ? Storage::url($v->path) : null),
            ])),
            'features' => $this->whenLoaded('features', fn () => $this->features->map(fn ($f) => [
                'name' => $f->name,
                'icon' => $f->icon,
            ])),
            'amenities' => AmenityResource::collection($this->whenLoaded('amenities')),
            'units' => UnitResource::collection($this->whenLoaded('units')),
            'seo' => $this->whenLoaded('seo', fn () => $this->seo ? [
                'title' => $this->seo->title,
                'meta_description' => $this->seo->meta_description,
                'canonical_url' => $this->seo->canonical_url,
                'og_image' => $this->seo->og_image ? Storage::url($this->seo->og_image) : null,
                'schema' => $this->seo->schema,
            ] : null),
        ];
    }
}
