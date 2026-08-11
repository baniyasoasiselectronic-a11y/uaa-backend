<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    protected $guarded = [];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
