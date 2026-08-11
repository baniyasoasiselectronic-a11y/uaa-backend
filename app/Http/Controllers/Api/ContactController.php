<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Models\ContactRequest;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request)
    {
        $contact = ContactRequest::create($request->validated());

        return response()->json([
            'message' => 'Thank you for reaching out. We will be in touch soon.',
            'id' => $contact->id,
        ], 201);
    }
}
