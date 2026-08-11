<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_number' => $this->unit_number,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'area_sqft' => $this->area_sqft,
            'price' => $this->price,
            'status' => $this->status,
            'floor' => $this->whenLoaded('floor', fn () => [
                'id' => $this->floor->id,
                'name' => $this->floor->name,
                'floor_number' => $this->floor->floor_number,
            ]),
        ];
    }
}
