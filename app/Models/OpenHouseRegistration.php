<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenHouseRegistration extends Model
{
    protected $guarded = [];

    public function openHouse(): BelongsTo
    {
        return $this->belongsTo(OpenHouse::class);
    }
}
