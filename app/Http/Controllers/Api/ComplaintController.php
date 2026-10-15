<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Mail\NewComplaintNotification;
use App\Models\Complaint;
use App\Support\ComplaintOracle;
use App\Support\UaaOracle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    public const VISIT_SLOTS = ['9:00 AM - 12:00 PM', '12:00 PM - 3:00 PM', '3:00 PM - 6:00 PM'];

    /** Lists for the website form: buildings, unit types and complaint types all come from Oracle; if it is down the form falls back to typing. */
    public function options()
    {
        $properties = [];
        foreach (UaaOracle::properties() as $id => $name) {
            $properties[] = ['id' => (string) $id, 'name' => $name];
        }

        return response()->json([
            'properties' => $properties,
            'unit_types' => UaaOracle::unitTypeCodes(),
            'types' => UaaOracle::complaintTypes(),
            'visit_slots' => self::VISIT_SLOTS,
        ]);
    }

    /** Units of one building as {id, no} (the id is Oracle's unit id, which Oracle needs back). No tenant details. */
    public function units(Request $request)
    {
        $rows = UaaOracle::unitRows((string) $request->query('property_id'));

        return response()->json(['units' => array_map(fn ($r) => ['id' => $r['unit_id'], 'no' => $r['unit_no']], $rows)]);
    }

    public function store(StoreComplaintRequest $request)
    {
        $data = $request->safe()->except('attachments');

        // Names and labels come from Oracle's lists, not from what the browser sent.
        if (! empty($data['oracle_property_id'])) {
            $data['property_name'] = UaaOracle::properties()[(string) $data['oracle_property_id']] ?? ($data['property_name'] ?? null);
        }
        if (! empty($data['complaint_type_code'])) {
            foreach (UaaOracle::complaintTypes() as $t) {
                if ($t['code'] === (string) $data['complaint_type_code']) {
                    $data['category'] = $t['label'];
                }
            }
        }
        if (empty($data['oracle_unit_id']) && ! empty($data['oracle_property_id']) && ! empty($data['unit_label'])) {
            $data['oracle_unit_id'] = UaaOracle::unitId((string) $data['oracle_property_id'], (string) $data['unit_label']);
        }

        // A double click or a retry on a slow connection must not file the complaint twice.
        $existing = Complaint::query()
            ->where('email', $data['email'])->where('description', $data['description'])
            ->where('oracle_property_id', $data['oracle_property_id'] ?? null)
            ->where('unit_label', $data['unit_label'] ?? null)
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')->first();
        if ($existing) {
            return response()->json([
                'message' => 'Your complaint has been logged.',
                'ticket_number' => $existing->complaint_number ?: $existing->ticket_number,
                'oracle_status' => $existing->oracle_status,
            ], 200);
        }

        $data['ticket_number'] = 'UAA-'.strtoupper(Str::random(8));
        $data['payment_status'] = 'not_required';
        $data['payment_ref'] = 'FREE-'.time();

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

        // Send it to Oracle now (the same system the old site posted to). If Oracle is unreachable the complaint
        // is still saved here and uaa:sync-complaints keeps retrying, so a tenant never sees an error for it.
        $posted = ComplaintOracle::submit($complaint);
        $complaint->refresh();

        // Email the facilities/complaints team. Never let a mail outage block the ticket itself.
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
            'message' => $posted
                ? 'Your complaint has been received.'
                : 'Your complaint has been logged. The maintenance team will confirm your complaint number shortly.',
            'ticket_number' => $complaint->complaint_number ?: $complaint->ticket_number,
            'oracle_status' => $complaint->oracle_status,
        ], 201);
    }
}
