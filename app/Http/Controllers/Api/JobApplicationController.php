<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobApplicationRequest;
use App\Models\JobApplication;

class JobApplicationController extends Controller
{
    /**
     * General career application (not tied to a specific opening).
     */
    public function store(StoreJobApplicationRequest $request)
    {
        $data = $request->safe()->except('cv');
        $data['cv_path'] = $request->file('cv')->store('job-applications', 'public');

        JobApplication::create($data);

        return response()->json([
            'message' => 'Thank you for your application. Our team will be in touch.',
        ], 201);
    }
}
