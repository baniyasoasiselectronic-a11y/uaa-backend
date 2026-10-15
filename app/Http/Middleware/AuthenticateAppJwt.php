<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\WpJson\AuthController;
use App\Support\AppJwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Signs a request in from the mobile app's "Authorization: Bearer <token>" header (token from /wp-json/custom/v1/login). */
class AuthenticateAppJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = $token ? AppJwt::user($token) : null;
        if (! $user) {
            return AuthController::wpError('rest_forbidden', 'Sorry, you are not allowed to do that.', 401);
        }
        auth()->setUser($user);

        return $next($request);
    }
}
