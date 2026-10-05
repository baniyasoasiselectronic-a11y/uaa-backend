<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'main_image' => $this->main_image ? $this->url($this->main_image) : null,
            'video_url' => $this->video_url,
            'virtual_tour_url' => $this->virtual_tour_url,
            'floor_plan' => $this->floor_plan ? $this->url($this->floor_plan) : null,
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
                'url' => $this->url($img->path),
                'alt' => $img->alt,
                'is_main' => (bool) $img->is_main,
            ])),
            'total_apartments' => $this->total_apartments,
            'built_up_sqm' => $this->built_up_sqm !== null ? (float) $this->built_up_sqm : null,
            'floors' => $this->whenLoaded('floors', fn () => $this->floors->map(fn ($f) => [
                'id' => $f->id,
                'label' => $f->label,
                'plan_url' => $f->plan_image ? $this->url($f->plan_image) : null,
                'total_apartments' => $f->total_apartments ?? $f->units->count(),
                'total_area_sqm' => $f->total_area_sqm !== null
                    ? (float) $f->total_area_sqm
                    : round($f->units->sum(fn ($u) => (float) $u->total_sqm), 2),
                'notes' => $f->notes,
                'units' => $f->units->map(fn ($u) => [
                    'id' => $u->id,
                    'type' => $u->unit_type,
                    'bedrooms' => $u->bedrooms,
                    'category' => \App\Models\PropertyFloorUnit::categoryLabel($u->bedrooms),
                    'suite_sqm' => $u->suite_sqm !== null ? (float) $u->suite_sqm : null,
                    'outdoor_label' => $u->outdoor_label,
                    'outdoor_sqm' => $u->outdoor_sqm !== null ? (float) $u->outdoor_sqm : null,
                    'total_sqm' => $u->total_sqm,
                    'url' => $u->image ? $this->url($u->image) : null,
                    'notes' => $u->notes,
                ])->values(),
            ])),
            'floor_plans' => $this->whenLoaded('floorPlans', fn () => $this->floorPlans->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'url' => $this->url($p->path),
                'area' => $p->area,
                'bedrooms' => $p->bedrooms,
                'notes' => $p->notes,
            ])),
            'updates' => $this->whenLoaded('updates', fn () => $this->updates->map(fn ($u) => [
                'id' => $u->id,
                'title' => $u->title ?: 'Construction Progress Update',
                'period' => $u->period_date->format('F Y'),
                'period_date' => $u->period_date->toDateString(),
                'notes' => $u->notes,
                'images' => $u->images->map(fn ($img) => [
                    'url' => $this->url($img->path),
                    'alt' => $img->alt,
                ]),
            ])),
            'videos' => $this->whenLoaded('videos', fn () => $this->videos->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'url' => $v->url ?: ($v->path ? $this->url($v->path) : null),
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
                'og_image' => $this->seo->og_image ? $this->url($this->seo->og_image) : null,
                'schema' => $this->seo->schema,
            ] : null),
        ];
    }

    private function url(string $path): string
    {
        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : Storage::url($path);
    }
}
