<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobApplicationRequest;
use App\Http\Resources\CareerResource;
use App\Models\Career;

class CareerController extends Controller
{
    public function index()
    {
        $careers = Career::query()
            ->where('is_open', true)
            ->latest()
            ->get();

        return CareerResource::collection($careers);
    }

    public function show(string $slug)
    {
        $career = Career::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return new CareerResource($career);
    }

    public function apply(StoreJobApplicationRequest $request, Career $career)
    {
        $data = $request->safe()->except('cv');
        $data['cv_path'] = $request->file('cv')->store('job-applications', 'public');
        $data['position'] = $data['position'] ?? $career->title;

        $career->applications()->create($data);

        return response()->json([
            'message' => 'Your application has been submitted. Thank you!',
        ], 201);
    }
}
