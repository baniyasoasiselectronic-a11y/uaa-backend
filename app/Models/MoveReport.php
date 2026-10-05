<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Move-in / move-out inspection report for a tenant's unit — carried over
 * from the old WordPress site's "Move In / Move Out" section.
 */
class MoveReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
        'photos' => 'array',
    ];

    public const TYPES = ['move_in' => 'Move In', 'move_out' => 'Move Out'];

    public const CONDITIONS = ['good' => 'Good', 'fair' => 'Fair', 'damaged' => 'Damaged', 'missing' => 'Missing', 'na' => 'N/A'];

    protected static function booted(): void
    {
        static::creating(function (self $report) {
            if (! $report->reference) {
                $prefix = $report->type === 'move_out' ? 'MO' : 'MI';
                do {
                    $ref = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
                } while (static::where('reference', $ref)->exists());
                $report->reference = $ref;
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MoveReportItem::class)->orderBy('sort_order');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum('charge');
    }

    /** @return list<string> */
    public function photoUrls(): array
    {
        return array_values(array_map(
            fn ($p) => str_starts_with($p, 'http') ? $p : Storage::disk('public')->url($p),
            array_filter((array) $this->photos)
        ));
    }
}
