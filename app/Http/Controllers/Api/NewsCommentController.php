<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comments on blog articles. Visitors can post; nothing shows on the site until a team member
 * approves it in the admin (Content → Comments). Links and the hidden "website" field mark spam.
 */
class NewsCommentController extends Controller
{
    private function article(string $slug): News
    {
        return News::where('slug', $slug)->where('is_published', true)->firstOrFail();
    }

    public function index(string $slug): JsonResponse
    {
        $news = $this->article($slug);

        return response()->json(['data' => NewsComment::where('news_id', $news->id)->where('status', 'approved')
            ->oldest()->limit(200)->get(['name', 'comment', 'created_at'])
            ->map(fn ($c) => ['name' => $c->name, 'comment' => $c->comment, 'date' => $c->created_at?->toIso8601String()])]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $news = $this->article($slug);
        $d = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'], // honeypot: real visitors leave it empty
        ]);

        $spam = filled($d['website'] ?? null) || preg_match_all('~https?://|www\.~i', $d['comment']) > 1;
        if (! filled($d['website'] ?? null)) {
            NewsComment::create([
                'news_id' => $news->id,
                'name' => strip_tags($d['name']),
                'email' => $d['email'],
                'comment' => strip_tags($d['comment']),
                'status' => $spam ? 'spam' : 'pending',
                'ip' => $request->ip(),
            ]);
        }

        return response()->json(['message' => 'Thank you — your comment is awaiting approval.'], 201);
    }
}
