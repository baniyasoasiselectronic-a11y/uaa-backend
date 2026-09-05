<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyListResource;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Paginated, filterable property listing for the search page.
     */
    public function index(Request $request)
    {
        $query = Property::query()
            ->where('is_published', true)
            ->with(['community', 'propertyType', 'amenities']);

        // Purpose: rent / sale
        if ($purpose = $request->query('purpose')) {
            $query->where('purpose', $purpose);
        }

        // Status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Upcoming / off-plan projects
        if ($request->boolean('upcoming')) {
            $query->whereIn('status', ['off_plan', 'coming_soon']);
        }

        // Property type by slug or id
        if ($type = $request->query('type')) {
            $query->whereHas('propertyType', function ($q) use ($type) {
                is_numeric($type) ? $q->where('id', $type) : $q->where('slug', $type);
            });
        }

        // Community by slug or id
        if ($community = $request->query('community')) {
            $query->whereHas('community', function ($q) use ($community) {
                is_numeric($community) ? $q->where('id', $community) : $q->where('slug', $community);
            });
        }

        // Bedrooms (minimum)
        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', (int) $request->query('bedrooms'));
        }

        // Price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        // Amenities checklist (slugs); property must have ALL selected
        $amenities = $request->query('amenities');
        if ($amenities) {
            $slugs = is_array($amenities) ? $amenities : explode(',', $amenities);
            foreach ($slugs as $slug) {
                $query->whereHas('amenities', fn ($q) => $q->where('slug', $slug));
            }
        }

        // Keyword search
        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('reference_code', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%");
            });
        }

        // Sorting
        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'featured' => $query->orderByDesc('is_featured')->latest(),
            default => $query->latest(),
        };

        $perPage = min((int) $request->query('per_page', 12), 48);

        return PropertyListResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Featured properties for the homepage carousel.
     */
    public function featured()
    {
        $properties = Property::query()
            ->where('is_published', true)
            ->where('is_featured', true)
            ->with(['community', 'propertyType', 'amenities'])
            ->latest()
            ->take(8)
            ->get();

        return PropertyListResource::collection($properties);
    }

    /**
     * Single property by slug.
     */
    public function show(string $slug)
    {
        $property = Property::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with([
                'community', 'propertyType', 'amenities', 'images', 'videos',
                'features', 'units.floor', 'seo',
            ])
            ->firstOrFail();

        $property->increment('views');

        return new PropertyResource($property);
    }
}
