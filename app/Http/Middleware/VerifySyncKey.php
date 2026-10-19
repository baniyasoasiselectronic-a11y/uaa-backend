<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the old WordPress site, which holds the shared key (WP_SYNC_KEY), may use /api/sync/*. Closed if no key is set. */
class VerifySyncKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('services.wp_sync.key');
        if (strlen($key) < 20) {
            return response()->json(['ok' => false, 'message' => 'Sync is not set up on this server.'], 503);
        }
        if (! hash_equals($key, (string) $request->header('X-Sync-Key'))) {
            return response()->json(['ok' => false, 'message' => 'Wrong sync key.'], 401);
        }

        Cache::forever('wp_sync:last_contact', time());

        return $next($request);
    }
}
