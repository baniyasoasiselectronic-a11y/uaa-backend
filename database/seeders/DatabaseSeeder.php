<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Community;
use App\Models\NewsCategory;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedAdmin();
        $this->seedPropertyTypes();
        $this->seedAmenities();
        $this->seedCommunities();
        $this->seedNewsCategories();
    }

    private function seedRoles(): void
    {
        foreach (User::STAFF_ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function seedAdmin(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@uaa.local'],
            [
                'name' => 'UAA Admin',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles(['super_admin']);
    }

    private function seedPropertyTypes(): void
    {
        $types = [
            ['name' => 'Apartment', 'category' => 'residential', 'icon' => 'building-2'],
            ['name' => 'Villa', 'category' => 'residential', 'icon' => 'home'],
            ['name' => 'Townhouse', 'category' => 'residential', 'icon' => 'houses'],
            ['name' => 'Penthouse', 'category' => 'residential', 'icon' => 'building'],
            ['name' => 'Office', 'category' => 'commercial', 'icon' => 'briefcase'],
            ['name' => 'Shop', 'category' => 'commercial', 'icon' => 'store'],
            ['name' => 'Warehouse', 'category' => 'commercial', 'icon' => 'warehouse'],
        ];

        foreach ($types as $i => $type) {
            PropertyType::updateOrCreate(
                ['slug' => Str::slug($type['name'])],
                array_merge($type, ['sort_order' => $i, 'is_active' => true]),
            );
        }
    }

    private function seedAmenities(): void
    {
        $amenities = [
            'Parking', 'Balcony', 'CCTV', 'Security', 'WiFi', 'Central AC',
            'Swimming Pool', 'Gym', 'Kids Play Area', 'Concierge', 'Covered Parking',
            'Pets Allowed', 'Maid Service', 'Shared Spa', 'BBQ Area',
        ];

        foreach ($amenities as $i => $name) {
            Amenity::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i],
            );
        }
    }

    private function seedCommunities(): void
    {
        $communities = [
            ['name' => 'Akasya East', 'tagline' => 'Modern living in the heart of Warsan', 'location' => 'Warsan, Dubai', 'is_featured' => true],
            ['name' => 'Akasya West', 'tagline' => 'Contemporary comfort and connectivity', 'location' => 'Warsan, Dubai', 'is_featured' => true],
            ['name' => 'Warsan', 'tagline' => 'A vibrant, family-friendly community', 'location' => 'Warsan, Dubai', 'is_featured' => true],
        ];

        foreach ($communities as $i => $community) {
            Community::updateOrCreate(
                ['slug' => Str::slug($community['name'])],
                array_merge($community, ['sort_order' => $i]),
            );
        }
    }

    private function seedNewsCategories(): void
    {
        foreach (['Company News', 'Market Insights', 'Community', 'Guides'] as $name) {
            NewsCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
