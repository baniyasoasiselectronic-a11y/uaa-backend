<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload sent by the voice AI (Retell/Vapi) after a phone call ends.
 * Only name + phone are required — voice-captured emails are unreliable.
 */
class StoreCallLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'property_type' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'string', 'max:100'],
            'timeline' => ['nullable', 'string', 'max:100'],
            'call_id' => ['nullable', 'string', 'max:255'],
            'transcript' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
