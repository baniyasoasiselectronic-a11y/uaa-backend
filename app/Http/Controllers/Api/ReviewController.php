<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReviewController extends Controller
{
    /**
     * Live Google reviews via the Google Places Details API.
     * Configure GOOGLE_PLACES_API_KEY + GOOGLE_PLACE_ID in .env.
     * Cached for a few hours to limit API cost.
     */
    public function index()
    {
        $key = config('services.google.places_key');
        $placeId = config('services.google.place_id');

        if (! $key || ! $placeId) {
            return response()->json(['configured' => false, 'reviews' => []]);
        }

        $payload = Cache::remember("google_reviews_{$placeId}", now()->addHours(3), function () use ($key, $placeId) {
            try {
                $res = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $placeId,
                    'key' => $key,
                    'reviews_sort' => 'newest',
                    'fields' => 'name,rating,user_ratings_total,reviews,url',
                ]);
                $result = $res->json('result') ?? [];

                return [
                    'configured' => true,
                    'name' => $result['name'] ?? null,
                    'rating' => $result['rating'] ?? null,
                    'total' => $result['user_ratings_total'] ?? null,
                    'url' => $result['url'] ?? "https://www.google.com/maps/place/?q=place_id:{$placeId}",
                    'reviews' => collect($result['reviews'] ?? [])->map(fn ($r) => [
                        'author' => $r['author_name'] ?? null,
                        'rating' => $r['rating'] ?? null,
                        'text' => $r['text'] ?? null,
                        'time' => $r['relative_time_description'] ?? null,
                        'photo' => $r['profile_photo_url'] ?? null,
                    ])->values(),
                ];
            } catch (\Throwable $e) {
                return ['configured' => true, 'reviews' => [], 'error' => true];
            }
        });

        return response()->json($payload);
    }
}
