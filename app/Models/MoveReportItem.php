<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveReportItem extends Model
{
    protected $guarded = [];

    protected $casts = ['charge' => 'decimal:2'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MoveReport::class, 'move_report_id');
    }
}
