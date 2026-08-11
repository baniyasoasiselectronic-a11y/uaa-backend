<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListWithUsRequest;
use App\Models\ListWithUsRequest;

class ListWithUsController extends Controller
{
    public function store(StoreListWithUsRequest $request)
    {
        $data = $request->safe()->except('document');

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('list-with-us', 'public');
        }

        $listing = ListWithUsRequest::create($data);

        return response()->json([
            'message' => 'Thank you. Our team will review your property and get in touch.',
            'id' => $listing->id,
        ], 201);
    }
}
