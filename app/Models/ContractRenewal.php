<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Tenancy contract renewal (carried over from the old "Renewal Form"). */
class ContractRenewal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
        'imported' => 'boolean',
    ];

    public function contractUrl(): ?string
    {
        $f = $this->contract_file;
        if (! $f) {
            return null;
        }

        return str_starts_with($f, 'http') ? $f : Storage::disk('public')->url($f);
    }
}
