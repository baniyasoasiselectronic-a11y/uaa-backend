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
            'images' => $this->imageUrls(),
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
        return Str::startsWith($path, ['http://', 'https://']) ? $path : Storage::url($path);
    }
}
