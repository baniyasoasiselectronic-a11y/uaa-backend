<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Community;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\OpenHouse;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $apartment = PropertyType::where('slug', 'apartment')->first();
        $villa = PropertyType::where('slug', 'villa')->first();
        $akasyaEast = Community::where('slug', 'akasya-east')->first();
        $warsan = Community::where('slug', 'warsan')->first();
        $amenityIds = Amenity::whereIn('slug', ['parking', 'balcony', 'security', 'central-ac', 'gym', 'swimming-pool'])->pluck('id');

        $demo = [
            [
                'title' => 'Akasya East — 2 Bedroom Apartment',
                'purpose' => 'rent', 'status' => 'available',
                'price' => 95000, 'bedrooms' => 2, 'bathrooms' => 2, 'area_sqft' => 1150,
                'type' => $apartment, 'community' => $akasyaEast, 'featured' => true,
                'description' => 'A bright, contemporary two-bedroom apartment in the heart of Akasya East, featuring an open-plan living space, fitted kitchen and a private balcony with community views.',
            ],
            [
                'title' => 'Warsan — Spacious 4 Bedroom Villa',
                'purpose' => 'sale', 'status' => 'available',
                'price' => 3200000, 'bedrooms' => 4, 'bathrooms' => 5, 'area_sqft' => 3800,
                'type' => $villa, 'community' => $warsan, 'featured' => true,
                'description' => 'An elegant four-bedroom family villa with a private garden, maid\'s room and covered parking, moments from schools and retail in Warsan.',
            ],
            [
                'title' => 'Akasya East — Studio Apartment',
                'purpose' => 'rent', 'status' => 'available',
                'price' => 48000, 'bedrooms' => 0, 'bathrooms' => 1, 'area_sqft' => 480,
                'type' => $apartment, 'community' => $akasyaEast, 'featured' => false,
                'description' => 'A smart, efficient studio ideal for professionals, with built-in wardrobes, a modern bathroom and access to community amenities.',
            ],
        ];

        foreach ($demo as $i => $d) {
            $property = Property::updateOrCreate(
                ['slug' => Str::slug($d['title'])],
                [
                    'title' => $d['title'],
                    'reference_code' => 'UAA-'.str_pad((string) ($i + 1001), 5, '0', STR_PAD_LEFT),
                    'property_type_id' => $d['type']?->id,
                    'community_id' => $d['community']?->id,
                    'purpose' => $d['purpose'],
                    'status' => $d['status'],
                    'description' => $d['description'],
                    'price' => $d['price'],
                    'currency' => 'AED',
                    'bedrooms' => $d['bedrooms'],
                    'bathrooms' => $d['bathrooms'],
                    'area_sqft' => $d['area_sqft'],
                    'address' => ($d['community']?->name ?? 'Dubai').', UAE',
                    'is_featured' => $d['featured'],
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );

            $property->amenities()->sync($amenityIds);

            $property->features()->delete();
            foreach (['Built-in Wardrobes', 'Fitted Kitchen', 'Covered Parking'] as $feature) {
                $property->features()->create(['name' => $feature]);
            }

            // A couple of available units per property.
            $property->units()->delete();
            for ($u = 1; $u <= 2; $u++) {
                $property->units()->create([
                    'unit_number' => sprintf('%02d-%02d', $i + 1, $u),
                    'bedrooms' => $d['bedrooms'],
                    'bathrooms' => $d['bathrooms'],
                    'area_sqft' => $d['area_sqft'],
                    'price' => $d['price'],
                    'status' => 'available',
                ]);
            }
        }

        // A demo news post.
        $category = NewsCategory::where('slug', 'market-insights')->first();
        $author = User::where('email', 'admin@uaa.local')->first();
        News::updateOrCreate(
            ['slug' => 'dubai-rental-market-2026-outlook'],
            [
                'news_category_id' => $category?->id,
                'author_id' => $author?->id,
                'title' => 'Dubai Rental Market: 2026 Outlook',
                'excerpt' => 'What tenants and investors can expect from Dubai\'s residential market this year.',
                'body' => '<p>The Dubai residential market continues to show resilience in 2026, with steady demand across communities like Warsan and Akasya.</p>',
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        // A demo open house.
        $firstProperty = Property::first();
        OpenHouse::updateOrCreate(
            ['slug' => 'akasya-east-open-house'],
            [
                'property_id' => $firstProperty?->id,
                'title' => 'Akasya East Open House',
                'description' => 'Tour our latest available apartments in Akasya East. Refreshments provided.',
                'starts_at' => now()->addDays(7)->setTime(11, 0),
                'ends_at' => now()->addDays(7)->setTime(15, 0),
                'location' => 'Akasya East, Warsan, Dubai',
                'capacity' => 50,
                'is_published' => true,
            ],
        );
    }
}
