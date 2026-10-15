<?php

namespace App\Http\Controllers\Api\WpJson;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AppJwt;
use App\Support\LegacyLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The mobile app's sign-in / sign-up, answering exactly like the old WordPress site did
 * (/wp-json/custom/v1/login, /custom/v1/register, /jwt-auth/v1/token, /jwt-auth/v1/token/validate),
 * so the Flutter app keeps working when uaa.ae moves to the new system.
 */
class AuthController extends Controller
{
    /** WordPress' own error shape, which is what the app is written against. */
    public static function wpError(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => ['status' => $status]], $status);
    }

    /** POST custom/v1/login  {email, password} */
    public function login(Request $request): JsonResponse
    {
        $user = LegacyLogin::attempt((string) $request->input('email'), (string) $request->input('password'));
        if (! $user instanceof User) {
            return self::wpError('login_failed', 'Login failed. Please check your credentials.', 401);
        }

        return response()->json([
            'status' => 'success',
            'user_id' => $user->legacy_wp_id ?: $user->id,
            'username' => $user->wp_login ?: $user->email,
            'email' => $user->email,
            'role' => $user->wp_role ?: 'subscriber',
            'token' => AppJwt::issue($user)['token'],
            'token_expiration' => '', // the old plugin never returned one; the app is built for an empty value
        ]);
    }

    /** POST custom/v1/register  {username, email, password}. Like the old site, always answers 200 with a message. */
    public function register(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:60', 'unique:users,wp_login'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:200'],
        ]);
        if ($v->fails()) {
            return response()->json(['message' => 'Failed to register user.']);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $user = User::create([
            'name' => trim((string) $request->input('username')),
            'email' => $email,
            'password' => (string) $request->input('password'),
            'locale' => 'en',
        ]);
        $user->forceFill(['wp_login' => trim((string) $request->input('username')), 'wp_role' => 'subscriber'])->save();

        return response()->json(['message' => 'User registered successfully. ']);
    }

    /** POST jwt-auth/v1/token  {username, password}; username may be the e-mail or the old WordPress username. */
    public function token(Request $request): JsonResponse
    {
        $result = LegacyLogin::attempt((string) $request->input('username'), (string) $request->input('password'));
        if ($result === LegacyLogin::UNKNOWN) {
            return self::wpError('[jwt_auth] invalid_username', '<strong>Error</strong>: Unknown username. Check again or try your email address.', 403);
        }
        if (! $result instanceof User) {
            return self::wpError('[jwt_auth] incorrect_password', '<strong>Error</strong>: The password you entered for the username <strong>'.e((string) $request->input('username')).'</strong> is incorrect.', 403);
        }

        return response()->json([
            'token' => AppJwt::issue($result)['token'],
            'user_email' => $result->email,
            'user_nicename' => $result->wp_login ?: $result->name,
            'user_display_name' => $result->name,
        ]);
    }

    /** POST jwt-auth/v1/token/validate  (Authorization: Bearer <token>) */
    public function validateToken(Request $request): JsonResponse
    {
        $header = (string) $request->header('Authorization');
        if ($header === '') {
            return self::wpError('jwt_auth_no_auth_header', 'Authorization header not found.', 403);
        }
        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $m) || ! AppJwt::user($m[1])) {
            return self::wpError('jwt_auth_invalid_token', 'Expired token', 403);
        }

        return response()->json(['code' => 'jwt_auth_valid_token', 'data' => ['status' => 200]]);
    }
}
