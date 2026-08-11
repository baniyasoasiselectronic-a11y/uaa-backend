<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Models\Community;

class CommunityController extends Controller
{
    public function index()
    {
        $communities = Community::query()
            ->withCount('properties')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        return CommunityResource::collection($communities);
    }

    public function show(string $slug)
    {
        $community = Community::query()
            ->where('slug', $slug)
            ->withCount('properties')
            ->with('neighborhoods')
            ->firstOrFail();

        return new CommunityResource($community);
    }
}
