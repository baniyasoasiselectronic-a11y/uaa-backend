<?php

namespace App\Http\Controllers\Api\WpJson;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Forgot password" for the mobile app, answering like the old "REST API Password Reset with Code" plugin
 * (/wp-json/bdpwr/v1/reset-password, /validate-code, /set-password): a code is e-mailed, it lives 15 minutes,
 * and it dies after 3 wrong tries. Codes are kept (hashed) in the cache, so no table is needed.
 */
class PasswordResetController extends Controller
{
    private const LIFETIME = 900; // 15 minutes
    private const MAX_TRIES = 3;

    /** The old plugin reported every failure as HTTP 500 with its own code, and the app is written against that. */
    private static function fail(string $code, string $message): JsonResponse
    {
        return AuthController::wpError($code, $message, 500);
    }

    private static function ok(string $message): JsonResponse
    {
        return response()->json(['data' => ['status' => 200], 'message' => $message]);
    }

    private static function key(string $email): string
    {
        return 'bdpwr:'.sha1($email);
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', strtoupper($code), (string) config('app.key'));
    }

    /** Staff sign in to the admin panel, not the app, so they can't reset through here (the old plugin also refused administrators). */
    private static function tenant(?string $email): ?User
    {
        $email = strtolower(trim((string) $email));
        $user = $email !== '' ? User::where('email', $email)->first() : null;

        return $user && ! $user->hasAnyRole(User::STAFF_ROLES) ? $user : null;
    }

    private static function newCode(): string
    {
        $length = max(4, min(12, (int) config('services.password_reset.code_length', 8)));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I so it is easy to read out of an e-mail
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    /** POST bdpwr/v1/reset-password  {email} */
    public function request(Request $request): JsonResponse
    {
        $user = self::tenant($request->input('email'));
        if (! $user) {
            return self::fail('bad_email', 'No user found with this email address.');
        }

        $code = self::newCode();
        Cache::put(self::key($user->email), ['hash' => self::hash($code), 'tries' => 0], self::LIFETIME);

        $text = "Hello {$user->name},\n\nYour United Arab Agencies password reset code is:\n\n    {$code}\n\n"
            ."Enter it in the app together with your new password. The code works for 15 minutes.\n"
            ."If you did not ask for this, you can ignore this e-mail — your password has not changed.\n\n"
            ."United Arab Agencies";
        try {
            Mail::raw($text, fn ($m) => $m->to($user->email)->subject('Your password reset code'));
        } catch (Throwable $e) {
            Log::warning('Password reset e-mail failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            Cache::forget(self::key($user->email));

            return self::fail('bad_email', 'The reset e-mail could not be sent. Please try again in a few minutes.');
        }

        return self::ok('A password reset email has been sent to your email address.');
    }

    /** True when the code is right; counts a wrong try and kills the code after the third. */
    private static function codeIsValid(string $email, string $code, bool &$requested): bool
    {
        $key = self::key($email);
        $entry = Cache::get($key);
        $requested = is_array($entry);
        if (! $requested) {
            return false;
        }
        if (hash_equals($entry['hash'], self::hash(trim($code)))) {
            return true;
        }
        $entry['tries']++;
        if ($entry['tries'] >= self::MAX_TRIES) {
            Cache::forget($key);
        } else {
            Cache::put($key, $entry, self::LIFETIME);
        }

        return false;
    }

    /** POST bdpwr/v1/validate-code  {email, code} */
    public function validateCode(Request $request): JsonResponse
    {
        $user = self::tenant($request->input('email'));
        $requested = false;
        if (! $user || ! self::codeIsValid($user->email, (string) $request->input('code'), $requested)) {
            return self::fail('bad_request', 'The reset code provided is not valid.');
        }

        return self::ok('The code supplied is valid.');
    }

    /** POST bdpwr/v1/set-password  {email, password, code} */
    public function setPassword(Request $request): JsonResponse
    {
        $user = self::tenant($request->input('email'));
        $requested = false;
        if (! $user) {
            return self::fail('bad_request', 'The reset code provided is not valid.');
        }
        $password = (string) $request->input('password');
        $valid = self::codeIsValid($user->email, (string) $request->input('code'), $requested);
        if (! $requested) {
            return self::fail('bad_request', 'You must request a password reset code before you try to set a new password.');
        }
        if (! $valid) {
            return self::fail('bad_request', 'The reset code provided is not valid.');
        }
        if (strlen($password) < 6 || strlen($password) > 200) {
            return self::fail('bad_request', 'The password must be at least 6 characters long.');
        }

        $user->forceFill(['password' => $password, 'wp_password_hash' => null])->save();
        Cache::forget(self::key($user->email));

        return self::ok('Password reset successfully.');
    }
}
