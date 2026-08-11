<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintAttachment extends Model
{
    protected $guarded = [];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
