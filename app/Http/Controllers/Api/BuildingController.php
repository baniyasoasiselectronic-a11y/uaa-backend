<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request)
    {
        $query = Building::query()
            ->with(['community', 'images' => fn ($q) => $q->orderBy('sort_order')])
            ->withAvg('units', 'price')
            ->orderBy('name');

        if ($community = $request->query('community')) {
            $query->whereHas('community', function ($q) use ($community) {
                is_numeric($community) ? $q->where('id', $community) : $q->where('slug', $community);
            });
        }

        return BuildingResource::collection($query->get());
    }

    public function show(string $slug)
    {
        $building = Building::query()
            ->where('slug', $slug)
            ->with(['community', 'images' => fn ($q) => $q->orderBy('sort_order')])
            ->withAvg('units', 'price')
            ->firstOrFail();

        return new BuildingResource($building);
    }
}
