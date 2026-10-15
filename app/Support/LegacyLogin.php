<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * One place that checks a tenant's login: e-mail (or the old WordPress username) + password.
 * Accounts brought over from WordPress keep their old password hash until the first successful sign-in,
 * when it is replaced by a normal Laravel hash and the old one is wiped.
 */
class LegacyLogin
{
    public const UNKNOWN = 'unknown';
    public const WRONG = 'wrong';

    /** @return User|string the user on success, otherwise self::UNKNOWN or self::WRONG */
    public static function attempt(string $login, string $password): User|string
    {
        $login = trim($login);
        $user = User::where('email', strtolower($login))->orWhere('wp_login', $login)->first();
        if (! $user) {
            return self::UNKNOWN;
        }

        if ($user->wp_password_hash && WpPassword::check($password, $user->wp_password_hash)) {
            // password carried over from the old site: switch the account to a normal hash
            $user->forceFill(['password' => $password, 'wp_password_hash' => null])->save();

            return $user;
        }

        return Hash::check($password, $user->password) ? $user : self::WRONG;
    }
}
