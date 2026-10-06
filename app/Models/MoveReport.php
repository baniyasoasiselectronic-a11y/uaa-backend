<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Move-in / move-out inspection report for a tenant's unit — carried over
 * from the old WordPress "Move In / Move Out" plugin. The room-by-room
 * checklist (status, notes, charge, photos) is stored in the `rooms` JSON
 * column, keyed by room id; see App\Support\MoveInspection.
 */
class MoveReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
        'rooms' => 'array',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public const TYPES = ['move_in' => 'Move In', 'move_out' => 'Move Out', 'renewal' => 'Renewal', 'legal' => 'Legal'];

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

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    /** Photo URLs for one room (full http(s) URLs pass through; paths resolve on the public disk). */
    public static function photoUrls(?array $paths): array
    {
        return array_values(array_map(
            fn ($p) => str_starts_with($p, 'http') ? $p : Storage::disk('public')->url($p),
            array_filter((array) $paths)
        ));
    }
}
