<?php

namespace App\Support;

use App\Models\User;

/**
 * The login token the Flutter app gets. Same shape as the old "JWT Authentication for WP-API" plugin:
 * an HS256 JWT with iss / iat / nbf / exp and data.user.id, valid for 7 days. data.user.id is the user's
 * old WordPress id (so anything in the app that reads it still sees the same number); "uid" is the id here.
 */
class AppJwt
{
    public const LIFETIME = 604800; // 7 days, the old plugin's default

    private static function secret(): string
    {
        return (string) (config('services.jwt.secret') ?: hash_hmac('sha256', 'uaa-wp-json-jwt', (string) config('app.key')));
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }

    /** @return array{token: string, exp: int} */
    public static function issue(User $user): array
    {
        $now = time();
        $exp = $now + self::LIFETIME;
        $payload = [
            'iss' => rtrim((string) config('app.url'), '/'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'uid' => $user->id,
            'data' => ['user' => ['id' => (string) ($user->legacy_wp_id ?: $user->id)]],
        ];
        $head = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = self::b64(json_encode($payload));
        $sig = self::b64(hash_hmac('sha256', "$head.$body", self::secret(), true));

        return ['token' => "$head.$body.$sig", 'exp' => $exp];
    }

    /** The user the token belongs to, or null when it is malformed, forged, expired or the user is gone. */
    public static function user(?string $token): ?User
    {
        $parts = explode('.', (string) $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$head, $body, $sig] = $parts;
        $header = json_decode((string) self::unb64($head), true);
        if (($header['alg'] ?? null) !== 'HS256') {
            return null;
        }
        if (! hash_equals(self::b64(hash_hmac('sha256', "$head.$body", self::secret(), true)), $sig)) {
            return null;
        }
        $p = json_decode((string) self::unb64($body), true);
        if (! is_array($p) || ($p['exp'] ?? 0) < time() || ($p['nbf'] ?? 0) > time() + 60 || empty($p['uid'])) {
            return null;
        }

        return User::find($p['uid']);
    }
}
