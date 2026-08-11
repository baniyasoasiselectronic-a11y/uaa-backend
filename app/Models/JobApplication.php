<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    protected $guarded = [];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
