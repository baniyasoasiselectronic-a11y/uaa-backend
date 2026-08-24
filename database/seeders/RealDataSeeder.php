<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Building;
use App\Models\Community;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealDataSeeder extends Seeder
{
    public function run(): void
    {
        $cities = Community::pluck('id', 'slug');            // slug => id
        $types = PropertyType::pluck('id', 'slug');          // slug => id
        $amenityIds = Amenity::pluck('id')->all();

        $this->seedBuildings($cities);
        $this->seedProperties($cities, $types, $amenityIds);
        $this->seedUpcoming($cities, $types, $amenityIds);
    }

    private function seedBuildings($cities): void
    {
        // name, city slug, floors, description, real average rent (from live UAA Bayut listings; null = no verified data, keep illustrative average)
        $buildings = [
            ['Dana Al Garhoud', 'al-garhoud', 12, 'A landmark residential tower in the heart of Al Garhoud, offering furnished studios to two-bedroom apartments moments from Dubai Airport and the Metro. A consistently in-demand address on the open rental market.', 69286],
            ['Warsan Akasya', 'warsan', 8, 'Modern family apartments in the Akasya community, International City Phase 2 (Warsan 4).', 58500],
            ['Akasya North', 'warsan', 8, 'Contemporary apartments on the north side of the Akasya community, International City Phase 2 (Warsan 4).', 52000],
            ['Akasya South', 'warsan', 8, 'Bright, well-connected apartments in southern Akasya, International City Phase 2 (Warsan 4).', 60000],
            ['Mamzar Centre', 'deira-hor-al-anz', 6, 'Mixed-use building in Hor Al Anz — also home to the UAA head office.', 79750],
            ['Baniyas Center', 'deira-hor-al-anz', 10, 'Well-established commercial address in Deira, leasing offices, shops and kiosk/counter spaces.', 36250],
            ['Blue Star', 'dubai-silicon-oasis', 7, 'Comfortable, well-appointed apartments in Dubai Silicon Oasis (DSO), a self-contained residential and tech community.', 72000],
            ['Port Saeed Building', 'deira-hor-al-anz', 8, 'Commercial and residential units near Deira City Centre.', null],
            ['Baraha Villas', 'al-warqa', 2, 'Spacious family villas with private gardens in Al Warqa.', null],
            ['Al Warqa Blue', 'al-warqa', 6, 'Modern apartments in the family district of Al Warqa.', 51667],
            ['Barsha Oasis', 'al-barsha', 9, 'Contemporary studio apartments in Al Barsha, close to Mall of the Emirates and Sheikh Zayed Road.', 55000],
            ['Al Fahidi Building', 'deira-hor-al-anz', 4, 'Heritage-area building in historic Bur Dubai / Al Fahidi.', null],
        ];

        // The old placeholder name/slug — rename in place if it still exists so we don't leave a duplicate.
        if ($old = Building::where('slug', 'al-barsha-residence')->first()) {
            $old->update(['slug' => 'barsha-oasis']);
        }

        foreach ($buildings as [$name, $citySlug, $floors, $desc, $realAvg]) {
            $building = Building::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'community_id' => $cities[$citySlug] ?? null,
                    'floors_count' => $floors,
                    'description' => $desc,
                ],
            );

            // A few illustrative units per building.
            $building->units()->delete();
            $configs = [
                ['A-101', 1, 1, 720, 55000, 'available'],
                ['A-204', 2, 2, 1150, 95000, 'available'],
                ['B-306', 3, 3, 1650, 140000, 'reserved'],
            ];
            foreach ($configs as [$no, $bd, $ba, $area, $price, $status]) {
                $building->units()->create([
                    'unit_number' => $no,
                    'bedrooms' => $bd,
                    'bathrooms' => $ba,
                    'area_sqft' => $area,
                    'price' => $price,
                    'status' => $status,
                ]);
            }

            // Building-level media + average price (units are never shown publicly).
            // Prefer a real, verified average from live UAA listings; fall back to the
            // illustrative unit configs above only when we have no verified figure.
            $prices = array_map(fn ($c) => $c[4], $configs);
            $gallery = $this->gallery();
            $building->update([
                'average_price' => $realAvg ?? round(array_sum($prices) / max(count($prices), 1)),
                'main_image' => $gallery[0],
            ]);
            $building->images()->delete();
            foreach (array_slice($gallery, 0, 4) as $gi => $g) {
                $building->images()->create(['path' => $g, 'sort_order' => $gi]);
            }
        }
    }

    private function gallery(): array
    {
        return ['gallery/arch.webp', 'gallery/dubai-frame.webp', 'gallery/palm.webp', 'gallery/reception.webp'];
    }

    private function seedProperties($cities, $types, array $amenityIds): void
    {
        // title, city slug, type slug, purpose, status, price, beds, baths, area, featured
        $properties = [
            ['Warsan Akasya — 2 Bedroom Apartment', 'warsan', 'apartment', 'rent', 'available', 95000, 2, 2, 1150, true],
            ['Dana Al Garhoud — 1 Bedroom Apartment', 'al-garhoud', 'apartment', 'rent', 'available', 78000, 1, 1, 820, true],
            ['Mamzar Centre — Studio', 'deira-hor-al-anz', 'apartment', 'rent', 'available', 48000, 0, 1, 480, false],
            ['Baraha Villas — 4 Bedroom Villa', 'al-warqa', 'villa', 'rent', 'available', 280000, 4, 5, 3800, true],
            ['Al Barsha — 3 Bedroom Apartment', 'al-barsha', 'apartment', 'rent', 'available', 130000, 3, 3, 1650, false],
            ['Port Saeed — Retail Shop', 'deira-hor-al-anz', 'shop', 'rent', 'available', 180000, 0, 1, 900, false],
        ];

        foreach ($properties as $i => [$title, $citySlug, $typeSlug, $purpose, $status, $price, $bd, $ba, $area, $featured]) {
            $this->makeProperty($cities, $types, $amenityIds, $title, $citySlug, $typeSlug, $purpose, $status, $price, $bd, $ba, $area, $featured, 1000 + $i);
        }
    }

    private function seedUpcoming($cities, $types, array $amenityIds): void
    {
        // Off-plan / coming-soon projects (these are the "Upcoming" section).
        $upcoming = [
            ['Akasya East', 'warsan', 'apartment', 'off_plan', 850000, 1, 2, 780],
            ['Akasya West', 'warsan', 'apartment', 'off_plan', 900000, 1, 2, 810],
            ['Jeddaf Star', 'al-jaddaf', 'apartment', 'coming_soon', 1200000, 2, 2, 1100],
            ['Jeddaf', 'al-jaddaf', 'apartment', 'coming_soon', 1100000, 2, 2, 1050],
        ];

        foreach ($upcoming as $i => [$title, $citySlug, $typeSlug, $status, $price, $bd, $ba, $area]) {
            $this->makeProperty($cities, $types, $amenityIds, $title, $citySlug, $typeSlug, 'rent', $status, $price, $bd, $ba, $area, true, 2000 + $i);
        }
    }

    private function makeProperty($cities, $types, array $amenityIds, string $title, string $citySlug, string $typeSlug, string $purpose, string $status, $price, $bd, $ba, $area, bool $featured, int $ref): void
    {
        $property = Property::updateOrCreate(
            ['slug' => Str::slug($title)],
            [
                'title' => $title,
                'reference_code' => 'UAA-'.$ref,
                'property_type_id' => $types[$typeSlug] ?? null,
                'community_id' => $cities[$citySlug] ?? null,
                'purpose' => $purpose,
                'status' => $status,
                'description' => 'A quality '.$typeSlug.' offered by United Arab Agencies in '.ucfirst(str_replace('-', ' ', $citySlug)).', Dubai. Contact our team to arrange a viewing.',
                'price' => $price,
                'currency' => 'AED',
                'bedrooms' => $bd,
                'bathrooms' => $ba,
                'area_sqft' => $area,
                'address' => ucfirst(str_replace('-', ' ', $citySlug)).', Dubai',
                'is_featured' => $featured,
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $property->amenities()->sync(collect($amenityIds)->shuffle()->take(6)->all());

        $property->features()->delete();
        foreach (['Built-in Wardrobes', 'Fitted Kitchen', 'Covered Parking', '24/7 Security'] as $f) {
            $property->features()->create(['name' => $f]);
        }

        $property->units()->delete();
        for ($u = 1; $u <= 2; $u++) {
            $property->units()->create([
                'unit_number' => sprintf('%02d-%02d', ($ref % 100), $u),
                'bedrooms' => $bd,
                'bathrooms' => $ba,
                'area_sqft' => $area,
                'price' => $price,
                'status' => 'available',
            ]);
        }

        // Upcoming projects are a visual showcase — give them an image gallery.
        if (in_array($status, ['off_plan', 'coming_soon'], true)) {
            $gallery = $this->gallery();
            $property->update(['main_image' => $gallery[0]]);
            $property->images()->delete();
            foreach (array_slice($gallery, 0, 3) as $gi => $g) {
                $property->images()->create(['path' => $g, 'is_main' => $gi === 0, 'sort_order' => $gi]);
            }
        }
    }
}
