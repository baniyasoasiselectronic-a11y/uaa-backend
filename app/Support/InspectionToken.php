<?php

namespace App\Support;

/**
 * Stateless access token for the inspection page: "<expiry>.<signature>",
 * signed with the app key. Issued after the inspector types the page password.
 */
class InspectionToken
{
    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', 'inspection|'.$payload, (string) config('app.key'));
    }

    public static function issue(): string
    {
        $exp = (string) (time() + 3600 * (int) config('inspection.token_hours', 12));

        return $exp.'.'.self::sign($exp);
    }

    public static function valid(?string $token): bool
    {
        if (! $token || substr_count($token, '.') !== 1) {
            return false;
        }
        [$exp, $sig] = explode('.', $token);

        return ctype_digit($exp) && (int) $exp > time() && hash_equals(self::sign($exp), $sig);
    }
}
