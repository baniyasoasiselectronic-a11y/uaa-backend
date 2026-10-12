<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsComment extends Model
{
    protected $guarded = [];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
