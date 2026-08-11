<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $query = News::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->with(['category', 'author'])
            ->latest('published_at');

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        return NewsResource::collection($query->paginate(min((int) $request->query('per_page', 9), 30)));
    }

    public function show(string $slug)
    {
        $news = News::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with(['category', 'author'])
            ->firstOrFail();

        return new NewsResource($news);
    }
}
