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
            return Cache::remember('oracle:'.$path, 3600, function () use ($base, $path) {
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

    /** Fallback list (what Oracle returned when this was written) so the form still opens if Oracle is unreachable. */
    private const COMPLAINT_TYPES = [
        '6' => 'Carpenter Work', '9' => 'Home Appliance', '11' => 'Plumbing Work', '12' => 'Ac Work', '13' => 'Electrical Work',
        '14' => 'Others', '15' => 'IT Work', '16' => 'Mason Work', '17' => 'Glass Work', '19' => 'Paint Work', '20' => 'Pest Control',
        '21' => 'Vacate Unit', '22' => 'Lift Work', '23' => 'Chiller Work', '27' => 'Alumnium Work', '28' => 'LED',
    ];

    /** @return list<array{code: string, label: string}> complaint types as Oracle defines them (the code is what Oracle expects) */
    public static function complaintTypes(): array
    {
        $out = [];
        foreach (self::fetch('/complaint_type/') as $t) {
            if (isset($t['LOOKUP_CODE'], $t['VALUE'])) {
                $out[] = ['code' => (string) $t['LOOKUP_CODE'], 'label' => (string) $t['VALUE']];
            }
        }
        if (! $out) {
            foreach (self::COMPLAINT_TYPES as $code => $label) {
                $out[] = ['code' => (string) $code, 'label' => $label];
            }
        }

        return $out;
    }

    /** @return list<array{code: string, label: string}> unit types with the code Oracle expects */
    public static function unitTypeCodes(): array
    {
        $out = [];
        foreach (self::fetch('/unit_type/') as $t) {
            if (! empty($t['VALUE'])) {
                $out[] = ['code' => (string) ($t['LOOKUP_CODE'] ?? $t['VALUE']), 'label' => (string) $t['VALUE']];
            }
        }

        return $out;
    }

    /**
     * Sends one complaint to Oracle (POST /post_complaint/, the same body the old site sent).
     * Returns the requisition number Oracle gives back (digits only), or null when it could not be created.
     * Never cached; never throws.
     */
    public static function postComplaint(array $payload, ?string &$error = null): ?string
    {
        $base = self::baseUrl();
        if (! $base) {
            $error = 'Oracle address is not set';

            return null;
        }
        try {
            $res = Http::timeout(20)
                ->withHeaders(['ngrok-skip-browser-warning' => '1', 'Accept' => 'application/json'])
                ->asJson()->post($base.'/post_complaint/', $payload);
            $body = trim((string) $res->body());
            if (! $res->successful()) {
                $error = 'Oracle answered HTTP '.$res->status();

                return null;
            }
            if (stripos($body, 'General error') !== false) {
                $error = 'Oracle reported a general error';

                return null;
            }
            $number = preg_replace('/\D+/', '', $body);
            if ($number === '') {
                $error = 'Oracle did not return a requisition number';

                return null;
            }

            return $number;
        } catch (Throwable $e) {
            $error = 'Could not reach Oracle ('.mb_substr($e->getMessage(), 0, 90).')';

            return null;
        }
    }

    /**
     * Current tenant of a unit (name / email / mobile) from the Oracle API. Never cached —
     * it is personal data and should always be the live value.
     *
     * @return array{name: string, email: string, phone: string}|null
     */
    public static function tenant(?string $propertyId, ?string $unitId): ?array
    {
        $base = self::baseUrl();
        if (! $base || ! $propertyId || ! $unitId || Cache::has('oracle:down')) {
            return null;
        }
        try {
            $rows = Http::timeout(10)
                ->withHeaders(['ngrok-skip-browser-warning' => '1', 'Accept' => 'application/json'])
                ->get($base.'/get_user_data/', ['property_id' => $propertyId, 'unit_id' => $unitId])
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }
        $t = is_array($rows) ? ($rows[0] ?? null) : null;
        if (! is_array($t) || blank($t['TENANT_NAME'] ?? null)) {
            return null;
        }

        return [
            'name' => trim((string) $t['TENANT_NAME']),
            'email' => trim((string) ($t['EMAIL'] ?? '')),
            'phone' => preg_replace('/[-\/\s]/', '', (string) ($t['MOBILE'] ?? '')),
        ];
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
