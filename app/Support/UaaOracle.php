<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only client for UAA's Oracle API (the same one the old WordPress
 * Move In/Out plugin used): buildings, units and unit types. Results are
 * cached for 10 minutes; if the API is unreachable everything returns empty
 * and the forms fall back to typing the values by hand.
 */
class UaaOracle
{
    public static function baseUrl(): ?string
    {
        $u = (string) config('inspection.oracle_url');

        return $u !== '' ? rtrim($u, '/') : null;
    }

    /** Inspector names for the inspection page dropdown. */
    public static function inspectors(): array
    {
        return (array) config('inspection.inspectors');
    }

    /** "Renewed by" names for Contract Renewals. */
    public static function renewalStaff(): array
    {
        return (array) config('inspection.renewal_staff');
    }

    /** @return array<int, array<string, mixed>> */
    private static function fetch(string $path): array
    {
        $base = self::baseUrl();
        if (! $base || Cache::has('oracle:down')) {
            return [];
        }

        try {
            return Cache::remember('oracle:'.$path, 600, function () use ($base, $path) {
                $json = Http::timeout(12)
                    ->withHeaders(['ngrok-skip-browser-warning' => '1', 'Accept' => 'application/json'])
                    ->get($base.$path)
                    ->throw()
                    ->json();
                if (! is_array($json)) {
                    throw new \RuntimeException('Unexpected response');
                }

                return $json;
            });
        } catch (Throwable) {
            Cache::put('oracle:down', true, 60);

            return [];
        }
    }

    /** @return array<string, string> property id => name */
    public static function properties(): array
    {
        $out = [];
        foreach (self::fetch('/units/') as $u) {
            if (isset($u['PROPERTY_ID'], $u['PROPERTY_NAME'])) {
                $out[(string) $u['PROPERTY_ID']] = self::title($u['PROPERTY_NAME']);
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /** @return array<string, string> unit no => label for a property */
    public static function units(?string $propertyId): array
    {
        if (! $propertyId) {
            return [];
        }
        $out = [];
        foreach (self::fetch('/units/') as $u) {
            if ((string) ($u['PROPERTY_ID'] ?? '') === (string) $propertyId && isset($u['UNIT_NO'])) {
                $out[(string) $u['UNIT_NO']] = (string) $u['UNIT_NO'];
            }
        }
        uksort($out, fn ($a, $b) => strnatcasecmp($a, $b));

        return $out;
    }

    /** Units of a building in the shape the inspection screens expect. */
    public static function unitRows(?string $propertyId): array
    {
        if (! $propertyId) {
            return [];
        }
        $out = [];
        foreach (self::fetch('/units/') as $u) {
            if ((string) ($u['PROPERTY_ID'] ?? '') === (string) $propertyId && isset($u['UNIT_NO'])) {
                $out[] = ['unit_id' => (string) ($u['UNIT_ID'] ?? $u['UNIT_NO']), 'unit_no' => (string) $u['UNIT_NO'], 'unit_type' => '', 'status' => ''];
            }
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a['unit_no'], $b['unit_no']));

        return $out;
    }

    /** Oracle unit id for a property + unit no (kept on the record for reference). */
    public static function unitId(?string $propertyId, ?string $unitNo): ?string
    {
        foreach (self::fetch('/units/') as $u) {
            if ((string) ($u['PROPERTY_ID'] ?? '') === (string) $propertyId && (string) ($u['UNIT_NO'] ?? '') === (string) $unitNo) {
                return isset($u['UNIT_ID']) ? (string) $u['UNIT_ID'] : null;
            }
        }

        return null;
    }

    /** @return array<string, string> label => label */
    public static function unitTypes(): array
    {
        $out = [];
        foreach (self::fetch('/unit_type/') as $t) {
            if (! empty($t['VALUE'])) {
                $out[$t['VALUE']] = $t['VALUE'];
            }
        }

        return $out;
    }

    public static function available(): bool
    {
        return count(self::properties()) > 0;
    }

    private static function title(string $s): string
    {
        // Oracle stores most names in capitals; show them as Title Case.
        return $s === strtoupper($s) ? ucwords(strtolower($s)) : $s;
    }
}
