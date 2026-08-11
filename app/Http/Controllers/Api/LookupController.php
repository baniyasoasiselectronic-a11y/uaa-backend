<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmenityResource;
use App\Models\Amenity;
use App\Models\PropertyType;

class LookupController extends Controller
{
    public function propertyTypes()
    {
        return response()->json(
            PropertyType::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'category', 'icon'])
        );
    }

    public function amenities()
    {
        return AmenityResource::collection(
            Amenity::orderBy('sort_order')->get()
        );
    }
}
