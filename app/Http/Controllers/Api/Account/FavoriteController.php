<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyListResource;
use App\Models\Property;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $propertyIds = $request->user()->favorites()->pluck('property_id');

        $properties = Property::whereIn('id', $propertyIds)
            ->with(['community', 'propertyType', 'amenities'])
            ->get();

        return PropertyListResource::collection($properties);
    }

    public function store(Request $request, Property $property)
    {
        $request->user()->favorites()->firstOrCreate([
            'property_id' => $property->id,
        ]);

        return response()->json(['message' => 'Added to favorites.', 'favorited' => true], 201);
    }

    public function destroy(Request $request, Property $property)
    {
        $request->user()->favorites()->where('property_id', $property->id)->delete();

        return response()->json(['message' => 'Removed from favorites.', 'favorited' => false]);
    }
}
