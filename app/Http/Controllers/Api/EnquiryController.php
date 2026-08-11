<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Enquiry;

class EnquiryController extends Controller
{
    public function store(StoreEnquiryRequest $request)
    {
        $enquiry = Enquiry::create($request->validated());

        // Feed the CRM pipeline: every enquiry becomes a new lead.
        $enquiry->lead()->create([
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'phone' => $enquiry->phone,
            'property_id' => $enquiry->property_id,
            'unit_id' => $enquiry->unit_id,
            'stage' => 'new',
        ]);

        return response()->json([
            'message' => 'Thank you for your enquiry. Our team will contact you shortly.',
            'id' => $enquiry->id,
        ], 201);
    }
}
