<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pulls exact coordinates out of a pasted Google Maps link, so staff only
 * have to paste the "Share" link of a building's pin. Handles long links
 * (…@25.2,55.3,17z / …!3d25.2!4d55.3 / ?q=25.2,55.3) and short share links
 * (maps.app.goo.gl/…), which are followed to their long form first.
 */
class GoogleMapsLink
{
    /** @return array{0: float, 1: float}|null  [latitude, longitude] */
    public static function coordinates(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // Plain "25.2048, 55.2708" pasted straight in.
        if (preg_match('/^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$/', $url, $m)) {
            return self::valid((float) $m[1], (float) $m[2]);
        }

        if (preg_match('#^https?://(maps\.app\.goo\.gl|goo\.gl/maps|g\.co/kgs|share\.google)#i', $url)) {
            $url = self::follow($url) ?? $url;
        }

        $decoded = urldecode($url);
        $patterns = [
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',                       // exact pin of the place
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',                           // map centre
            '/[?&](?:q|query|ll|center|destination)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $decoded, $m)) {
                return self::valid((float) $m[1], (float) $m[2]);
            }
        }

        return null;
    }

    private static function valid(float $lat, float $lng): ?array
    {
        return abs($lat) <= 90 && abs($lng) <= 180 ? [round($lat, 7), round($lng, 7)] : null;
    }

    /** Follow redirects by hand (max 6 hops) and return the final URL. */
    private static function follow(string $url): ?string
    {
        try {
            for ($i = 0; $i < 6; $i++) {
                $res = Http::withoutRedirecting()->timeout(8)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (UAA map resolver)'])
                    ->get($url);
                $next = $res->header('Location');
                if (! $next) {
                    return $url;
                }
                if (str_starts_with($next, '/')) {
                    $p = parse_url($url);
                    $next = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').$next;
                }
                $url = $next;
                if (preg_match('/@-?\d+\.\d+,-?\d+\.\d+|!3d-?\d+\.\d+!4d/', urldecode($url))) {
                    return $url;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return $url;
    }
}
