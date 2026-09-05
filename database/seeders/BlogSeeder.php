<?php

namespace Database\Seeders;

use App\Models\News;
use App\Models\NewsCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'admin@uaa.local')->first();
        $company = NewsCategory::firstOrCreate(['slug' => 'company-news'], ['name' => 'Company News']);
        $guides = NewsCategory::firstOrCreate(['slug' => 'guides'], ['name' => 'Guides']);

        $blogs = [
            [
                'title' => 'Six Decades of Building Dubai: The United Arab Agencies Story',
                'category_id' => $company->id,
                'cover' => 'gallery/reception.webp',
                'excerpt' => 'From Deira\'s first three-storey building in 1964 to today\'s waterfront communities — the UAA journey of trust, craft and comfort.',
                'body' => "<p>United Arab Agencies has been part of Dubai's story since 1964. In an era when Deira was the beating heart of a young, ambitious city, UAA built Deira's first three-storey building — a landmark moment that helped shape the skyline residents know today.</p>"
                    ."<p>Six decades on, that same commitment to craftsmanship and community guides everything we do. We are not simply a real-estate agency; we are custodians of homes, neighbourhoods, and the relationships that hold them together — from a resident's first viewing to long after they have moved in.</p>"
                    ."<h3>Where comfort meets class</h3>"
                    ."<p>Across Deira, Al Garhoud, Warsan, Al Warqa and beyond, our buildings are designed around how Dubai actually lives: connected, family-friendly, and built to last. As the city looks to the future with new waterfront and off-plan communities, UAA continues to build what comes next.</p>"
                    ."<p>Whatever you are looking for — a first home, a family residence, or an investment — our team is here to help you find your place in Dubai.</p>",
            ],
            [
                'title' => "A Local's Guide to Living in Warsan",
                'category_id' => $guides->id,
                'cover' => 'gallery/arch.webp',
                'excerpt' => 'Green, family-friendly and superbly connected — here is what makes Warsan one of Dubai\'s most liveable communities.',
                'body' => "<p>Warsan has quietly become one of Dubai's most liveable communities — a place where value, space and connectivity meet. It is home to UAA's Akasya developments and a growing number of families who want more room to breathe without leaving the city behind.</p>"
                    ."<h3>Everyday convenience</h3>"
                    ."<p>Residents enjoy easy access to parks, schools, retail and community mosques, with major road links putting the rest of Dubai within comfortable reach. It is the kind of neighbourhood where daily life simply works.</p>"
                    ."<h3>A community on the rise</h3>"
                    ."<p>With new residences and upcoming projects such as Akasya East and Akasya West, Warsan offers both established comfort and clear growth potential — a rare combination for residents and investors alike.</p>"
                    ."<p>Thinking about making Warsan home? Speak to the UAA team to explore what's available today and what's coming soon.</p>",
            ],
        ];

        foreach ($blogs as $b) {
            News::updateOrCreate(
                ['slug' => Str::slug($b['title'])],
                [
                    'news_category_id' => $b['category_id'],
                    'author_id' => $author?->id,
                    'title' => $b['title'],
                    'excerpt' => $b['excerpt'],
                    'body' => $b['body'],
                    'cover_image' => $b['cover'],
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
