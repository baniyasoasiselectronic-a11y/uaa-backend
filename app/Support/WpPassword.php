<?php

namespace App\Support;

/**
 * Checks a password against a hash made by the old WordPress site, so tenants keep their password.
 * Handles every format WordPress has used:
 *   $P$ / $H$   phpass (WordPress up to 6.7)
 *   $wp$2y$     bcrypt over an HMAC-SHA384 pre-hash (WordPress 6.8+)
 *   $2y$ / $2a$ plain bcrypt
 *   32 hex      very old plain md5
 */
class WpPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function check(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '' || $password === '' || strlen($password) > 4096) {
            return false;
        }

        if (str_starts_with($hash, '$wp$')) {
            return password_verify(base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true)), substr($hash, 3));
        }
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return self::phpass($password, $hash);
        }
        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            return password_verify($password, $hash);
        }
        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals(strtolower($hash), md5($password));
        }

        return false;
    }

    private static function phpass(string $password, string $stored): bool
    {
        if (strlen($stored) !== 34) {
            return false;
        }
        $log2 = strpos(self::ITOA64, $stored[3]);
        if ($log2 === false || $log2 < 7 || $log2 > 30) {
            return false;
        }
        $salt = substr($stored, 4, 8);
        if (strlen($salt) !== 8) {
            return false;
        }

        $count = 1 << $log2;
        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return hash_equals($stored, substr($stored, 0, 12).self::encode64($hash, 16));
    }

    private static function encode64(string $input, int $count): string
    {
        $itoa = self::ITOA64;
        $out = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $out .= $itoa[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $out .= $itoa[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $out .= $itoa[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $out .= $itoa[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $out;
    }
}
