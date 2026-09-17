<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Mail\NewComplaintNotification;
use App\Models\Complaint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public function store(StoreComplaintRequest $request)
    {
        $data = $request->safe()->except('attachments');
        $data['ticket_number'] = 'UAA-'.strtoupper(Str::random(8));

        if ($user = $request->user()) {
            $data['customer_id'] = $user->id;
        }

        $complaint = Complaint::create($data);

        foreach ($request->file('attachments', []) as $file) {
            $complaint->attachments()->create([
                'path' => $file->store('complaint-attachments', 'public'),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        // Email the facilities/complaints team (mirrors the old uaa.ae WordPress
        // site's WP Mail SMTP setup). Never let a mail outage block the ticket
        // itself — the complaint is already saved above either way.
        $notifyEmail = config('services.complaints.notify_email');
        if ($notifyEmail) {
            try {
                Mail::to($notifyEmail)->send(new NewComplaintNotification($complaint));
            } catch (\Throwable $e) {
                Log::error('Failed to send complaint notification email', [
                    'complaint_id' => $complaint->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Your complaint has been logged.',
            'ticket_number' => $complaint->ticket_number,
        ], 201);
    }
}
