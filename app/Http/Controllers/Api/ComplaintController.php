<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Mail\NewComplaintNotification;
use App\Models\Complaint;
use App\Support\UaaOracle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    /** Complaint types offered on the website form. */
    public const TYPES = [
        'Air conditioning', 'Plumbing / Water leak', 'Electrical', 'Carpentry / Doors / Windows', 'Painting / Walls & Ceiling',
        'Appliances', 'Pest control', 'Lift / Elevator', 'Parking', 'Cleaning / Common areas', 'Noise / Neighbour',
        'Security', 'Billing / Payments', 'Staff conduct', 'Other',
    ];

    public const VISIT_SLOTS = ['9:00 AM - 12:00 PM', '12:00 PM - 3:00 PM', '3:00 PM - 6:00 PM'];

    /** Lists for the website form: buildings and unit types come from Oracle; if it is down the form falls back to typing. */
    public function options()
    {
        $properties = [];
        foreach (UaaOracle::properties() as $id => $name) {
            $properties[] = ['id' => (string) $id, 'name' => $name];
        }

        return response()->json([
            'properties' => $properties,
            'unit_types' => array_values(UaaOracle::unitTypes()),
            'types' => self::TYPES,
            'visit_slots' => self::VISIT_SLOTS,
        ]);
    }

    /** Unit numbers of one building (no tenant details). */
    public function units(Request $request)
    {
        $rows = UaaOracle::unitRows((string) $request->query('property_id'));

        return response()->json(['units' => array_map(fn ($r) => $r['unit_no'], $rows)]);
    }

    public function store(StoreComplaintRequest $request)
    {
        $data = $request->safe()->except('attachments');
        $data['ticket_number'] = 'UAA-'.strtoupper(Str::random(8));

        // The building name comes from the Oracle list, not from what the browser sent.
        if (! empty($data['oracle_property_id'])) {
            $data['property_name'] = UaaOracle::properties()[(string) $data['oracle_property_id']] ?? ($data['property_name'] ?? null);
        }

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
