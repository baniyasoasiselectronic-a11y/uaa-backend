<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NeighborhoodResource;
use App\Models\Neighborhood;

class NeighborhoodController extends Controller
{
    public function index()
    {
        $neighborhoods = Neighborhood::query()
            ->with('community')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        return NeighborhoodResource::collection($neighborhoods);
    }

    public function show(string $slug)
    {
        $neighborhood = Neighborhood::query()
            ->where('slug', $slug)
            ->with('community')
            ->firstOrFail();

        return new NeighborhoodResource($neighborhood);
    }
}
