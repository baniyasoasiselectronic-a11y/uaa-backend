<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, read-only API answers (buildings, properties, news, lists for forms...) are the same for every visitor and
 * change rarely, so the finished answer is kept for 2 minutes. A visitor then gets it in a fraction of a second
 * instead of waiting for the database again, and the browser may reuse it for a minute while moving between pages.
 * Edits made in the admin panel therefore appear on the website within about 2 minutes. Only successful GET answers
 * are kept; everything else passes through untouched.
 */
class CachePublicApi
{
    private const TTL = 120;

    private const BROWSER = 'public, max-age=60, stale-while-revalidate=300';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }
        $key = 'apicache:'.sha1($request->fullUrl());

        if (is_array($hit = Cache::get($key))) {
            return response($hit['body'], 200, ['Content-Type' => $hit['type'], 'Cache-Control' => self::BROWSER, 'X-Cache' => 'HIT']);
        }

        $response = $next($request);
        $type = (string) $response->headers->get('Content-Type');
        if ($response->getStatusCode() === 200 && str_contains($type, 'json')) {
            Cache::put($key, ['body' => $response->getContent(), 'type' => $type], self::TTL);
            $response->headers->set('Cache-Control', self::BROWSER);
            $response->headers->set('X-Cache', 'MISS');
        }

        return $response;
    }
}
