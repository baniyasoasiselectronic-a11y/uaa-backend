<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCallLeadRequest;
use App\Models\Enquiry;

/**
 * Receives the lead captured by the voice AI (Retell/Vapi) once a phone
 * call ends. Configure this URL as the "Post-Call Webhook" in Retell,
 * with the custom analysis field names matching StoreCallLeadRequest
 * (name, phone, email, property_type, location, budget, timeline).
 *
 * Creates the same Enquiry + Lead records the website forms create,
 * tagged source=ai_call so they're distinguishable in the admin.
 */
class CallLeadController extends Controller
{
    public function store(StoreCallLeadRequest $request)
    {
        $data = $request->validated();

        $summary = collect([
            'Property type' => $data['property_type'] ?? null,
            'Location' => $data['location'] ?? null,
            'Budget' => $data['budget'] ?? null,
            'When needed' => $data['timeline'] ?? null,
        ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

        $message = 'AI phone call enquiry'.($summary ? " — {$summary}" : '');
        if (! empty($data['call_id'])) {
            $message .= " (Call ID: {$data['call_id']})";
        }
        if (! empty($data['transcript'])) {
            $message .= "\n\nTranscript:\n{$data['transcript']}";
        }

        $enquiry = Enquiry::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? 'no-email-provided@uaa.ae',
            'phone' => $data['phone'],
            'message' => $message,
            'source' => 'ai_call',
        ]);

        $enquiry->lead()->create([
            'name' => $enquiry->name,
            'email' => $data['email'] ?? null,
            'phone' => $enquiry->phone,
            'stage' => 'new',
            'notes' => $summary ?: null,
        ]);

        return response()->json([
            'message' => 'Lead captured.',
            'id' => $enquiry->id,
        ], 201);
    }
}
