<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuildingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // average_price is admin-set; if empty, fall back to the average unit
        // price computed server-side (units themselves are never exposed).
        $avg = $this->average_price ?? ($this->units_avg_price ?? null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'average_price' => $avg !== null ? (float) $avg : null,
            'currency' => 'AED',
            'floors_count' => $this->floors_count,
            'year_built' => $this->year_built,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'map_url' => $this->map_url,
            'images' => $this->imageUrls(),
            'total_apartments' => $this->total_apartments,
            'built_up_sqm' => $this->built_up_sqm !== null ? (float) $this->built_up_sqm : null,
            // floor-plan explorer data; only sent on the single-building endpoint (same shape as a project's floors)
            'floors' => $this->whenLoaded('planFloors', fn () => $this->planFloors->map(fn ($f) => [
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
            ])->values()),
            'community' => $this->whenLoaded('community', fn () => $this->community ? [
                'id' => $this->community->id,
                'name' => $this->community->name,
                'slug' => $this->community->slug,
                'location' => $this->community->location,
                'latitude' => $this->community->latitude,
                'longitude' => $this->community->longitude,
                'amenities' => $this->community->amenities ?: [],
            ] : null),
        ];
    }

    private function imageUrls(): array
    {
        $urls = [];
        if ($this->main_image) {
            $urls[] = $this->url($this->main_image);
        }
        if ($this->relationLoaded('images')) {
            foreach ($this->images as $img) {
                $urls[] = $this->url($img->path);
            }
        }
        return array_values(array_unique($urls));
    }

    private function url(string $path): string
    {
        return \App\Support\Img::optimize(Str::startsWith($path, ['http://', 'https://']) ? $path : Storage::url($path));
    }
}
