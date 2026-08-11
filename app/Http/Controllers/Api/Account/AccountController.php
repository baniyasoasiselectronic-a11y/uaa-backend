<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Enquiries the customer submitted (matched on their account email).
     */
    public function enquiries(Request $request)
    {
        $enquiries = Enquiry::where('email', $request->user()->email)
            ->with('property:id,title,slug')
            ->latest()
            ->get();

        return response()->json($enquiries);
    }

    /**
     * Complaints belonging to the authenticated customer.
     */
    public function complaints(Request $request)
    {
        $complaints = $request->user()
            ->complaints()
            ->with(['property:id,title,slug', 'attachments'])
            ->latest()
            ->get();

        return response()->json($complaints);
    }
}
