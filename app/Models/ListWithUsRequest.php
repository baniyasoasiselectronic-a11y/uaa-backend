<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListWithUsRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expected_price' => 'decimal:2',
    ];
}
