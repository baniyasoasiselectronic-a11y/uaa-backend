<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCallbackRequest;
use App\Models\CallbackRequest;

class CallbackController extends Controller
{
    public function store(StoreCallbackRequest $request)
    {
        $callback = CallbackRequest::create($request->validated());

        return response()->json([
            'message' => 'We have received your request and will call you back shortly.',
            'id' => $callback->id,
        ], 201);
    }
}
