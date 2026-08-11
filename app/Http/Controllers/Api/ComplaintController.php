<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Models\Complaint;
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

        return response()->json([
            'message' => 'Your complaint has been logged.',
            'ticket_number' => $complaint->ticket_number,
        ], 201);
    }
}
