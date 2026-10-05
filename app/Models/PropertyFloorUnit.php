<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyFloorUnit extends Model
{
    protected $guarded = [];

    public function floor(): BelongsTo
    {
        return $this->belongsTo(PropertyFloor::class, 'property_floor_id');
    }

    /** Suite + balcony/terrace, so admins only ever type the two source numbers. */
    public function getTotalSqmAttribute(): ?float
    {
        if ($this->suite_sqm === null && $this->outdoor_sqm === null) {
            return null;
        }

        return round((float) $this->suite_sqm + (float) $this->outdoor_sqm, 2);
    }

    public static function categoryLabel(?int $bedrooms): ?string
    {
        if ($bedrooms === null) {
            return null;
        }

        return $bedrooms === 0 ? 'Studio' : "{$bedrooms} BHK";
    }
}
