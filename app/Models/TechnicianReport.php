<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Technician daily report: the complaints a technician attended on one day. */
class TechnicianReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
        'entries' => 'array',
        'imported' => 'boolean',
    ];
}
