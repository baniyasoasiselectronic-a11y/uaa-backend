<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'department' => $this->department,
            'location' => $this->location,
            'type' => $this->type,
            'description' => $this->when($request->routeIs('*.show'), $this->description),
            'requirements' => $this->when($request->routeIs('*.show'), $this->requirements),
            'is_open' => (bool) $this->is_open,
        ];
    }
}
