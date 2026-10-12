<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Copies one blog post from the old WordPress site into the new News list, using the old site's
 * public REST API (nothing is written to WordPress). Safe to run again: it updates the same article.
 *   php artisan uaa:import-wp-post
 *   php artisan uaa:import-wp-post --slug=some-old-slug --category=Guides
 */
class ImportWpPost extends Command
{
    protected $signature = 'uaa:import-wp-post
        {--slug=exploring-the-dubai-property-blog-your-gateway-to-real-estate-insights : slug of the old post}
        {--site=https://uaa.ae : old site address}
        {--category=Guides : news category to file it under (created if missing)}';

    protected $description = 'Copy a blog post from the old WordPress site into the new site';

    public function handle(): int
    {
        $slug = (string) $this->option('slug');
        $res = Http::timeout(30)->acceptJson()->get(rtrim($this->option('site'), '/').'/wp-json/wp/v2/posts', ['slug' => $slug, '_fields' => 'slug,title,content,excerpt,date']);
        $post = $res->successful() ? ($res->json()[0] ?? null) : null;
        if (! $post) {
            $this->error("Could not find the old post \"{$slug}\" ({$res->status()}).");

            return self::FAILURE;
        }

        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($post['excerpt']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5)));
        if ($text === '') {
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($post['content']['rendered']), ENT_QUOTES | ENT_HTML5)));
        }
        $category = NewsCategory::firstOrCreate(['slug' => Str::slug($this->option('category'))], ['name' => $this->option('category')]);

        $news = News::updateOrCreate(['slug' => $post['slug']], [
            'title' => html_entity_decode($post['title']['rendered'], ENT_QUOTES | ENT_HTML5),
            'excerpt' => Str::limit($text, 240, '…'),
            'body' => $post['content']['rendered'],
            'news_category_id' => $category->id,
            'is_published' => true,
            'published_at' => $post['date'],
        ]);

        $this->info("Imported: {$news->title}");

        return self::SUCCESS;
    }
}
