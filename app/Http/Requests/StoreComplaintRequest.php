<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:50'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'oracle_property_id' => ['nullable', 'string', 'max:50'],
            'oracle_unit_id' => ['nullable', 'string', 'max:50'],
            'unit_type_code' => ['nullable', 'string', 'max:10'],
            'complaint_type_code' => ['nullable', 'string', 'max:10'],
            'property_name' => ['nullable', 'string', 'max:255'],
            'unit_type' => ['nullable', 'string', 'max:60'],
            'unit_label' => ['nullable', 'string', 'max:60'],
            'visit_date' => ['nullable', 'date', 'after_or_equal:today'],
            'visit_time_range' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ];
    }
}
