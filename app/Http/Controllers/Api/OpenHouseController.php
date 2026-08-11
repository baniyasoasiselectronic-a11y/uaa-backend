<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOpenHouseRegistrationRequest;
use App\Http\Resources\OpenHouseResource;
use App\Models\OpenHouse;

class OpenHouseController extends Controller
{
    public function index()
    {
        $openHouses = OpenHouse::query()
            ->where('is_published', true)
            ->with('property')
            ->orderBy('starts_at')
            ->get();

        return OpenHouseResource::collection($openHouses);
    }

    public function show(string $slug)
    {
        $openHouse = OpenHouse::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('property')
            ->firstOrFail();

        return new OpenHouseResource($openHouse);
    }

    public function register(StoreOpenHouseRegistrationRequest $request, OpenHouse $openHouse)
    {
        $registration = $openHouse->registrations()->create($request->validated());

        return response()->json([
            'message' => 'Your registration has been received. See you there!',
            'id' => $registration->id,
        ], 201);
    }
}
