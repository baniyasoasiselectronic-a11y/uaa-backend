<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only client for UAA's Oracle API (the same one the old WordPress
 * Move In/Out plugin used): buildings, units and unit types. Results are
 * cached for 10 minutes; if the API is unreachable everything returns empty
 * and the inspection form falls back to typing the values by hand.
 */
class UaaOracle
{
    public const URL_KEY = 'move_oracle_api_url';

    public const INSPECTORS_KEY = 'move_inspectors';

    public static function setting(string $key, ?string $default = null): ?string
    {
        return Setting::where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'move_in_out', 'type' => 'text']);
        Cache::forget('oracle:/units/');
        Cache::forget('oracle:/unit_type/');
        Cache::forget('oracle:down');
    }

    public static function baseUrl(): ?string
    {
        $u = trim((string) self::setting(self::URL_KEY));

        return $u !== '' ? rtrim($u, '/') : null;
    }

    /** Inspector names from Settings, one per line. */
    public static function inspectors(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) self::setting(self::INSPECTORS_KEY)))));
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

    /** Oracle unit id for a property + unit no (kept on the report for reference). */
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

    /** Quick connectivity check for the Settings page (bypasses the cache). */
    public static function test(): array
    {
        $base = self::baseUrl();
        if (! $base) {
            return [false, 'No API address saved yet.'];
        }
        try {
            $h = ['ngrok-skip-browser-warning' => '1', 'Accept' => 'application/json'];
            $units = Http::timeout(20)->withHeaders($h)->get($base.'/units/')->throw()->json();
            $types = Http::timeout(20)->withHeaders($h)->get($base.'/unit_type/')->throw()->json();
            if (! is_array($units) || ! is_array($types)) {
                return [false, 'The address answered, but not with the expected data.'];
            }
            Cache::forget('oracle:/units/');
            Cache::forget('oracle:/unit_type/');
            $props = count(array_unique(array_column($units, 'PROPERTY_ID')));

            return [true, count($units).' units in '.$props.' buildings, '.count($types).' unit types.'];
        } catch (Throwable $e) {
            return [false, 'Could not reach the API: '.$e->getMessage()];
        }
    }

    private static function title(string $s): string
    {
        // Oracle stores most names in capitals; show them as Title Case.
        return $s === strtoupper($s) ? ucwords(strtolower($s)) : $s;
    }
}
