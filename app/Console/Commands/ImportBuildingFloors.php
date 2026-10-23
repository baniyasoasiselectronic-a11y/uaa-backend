<?php

namespace App\Console\Commands;

use App\Models\Building;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Loads a finished building's floors + apartment types (the floor-plan explorer on its building page) from
 * database/data/floors/buildings/<slug>/manifest.json and its images folder. Same idea as uaa:import-floors for
 * upcoming projects. A manifest may point at another building's images with "images_dir" (Akasya South uses
 * Akasya North's drawings). Uses Cloudinary when CLOUDINARY_URL is set (production), local disk otherwise.
 */
class ImportBuildingFloors extends Command
{
    protected $signature = 'uaa:import-building-floors {slug : Building slug, e.g. blue-star}
                            {--replace : Delete the building\'s existing floor plans first}
                            {--dry-run : Show what would be imported without uploading or saving}';

    protected $description = 'Import floor plans for a finished building (Blue Star, Akasya North/South…) from its manifest';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $dir = database_path("data/floors/buildings/{$slug}");
        $manifestPath = "{$dir}/manifest.json";

        if (! is_file($manifestPath)) {
            $this->error("No manifest found at {$manifestPath}");

            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $building = Building::where('slug', $slug)->first();
        if (! $building) {
            $this->error("No building with slug '{$slug}'.");

            return self::FAILURE;
        }
        if (! \Illuminate\Support\Facades\Schema::hasColumn('property_floors', 'building_id')) {
            $this->error('The database is not ready for building floor plans yet. Run: php artisan migrate --force');

            return self::FAILURE;
        }

        if ($building->planFloors()->exists() && ! $this->option('replace')) {
            $this->error("'{$slug}' already has floor plans. Re-run with --replace to rebuild them.");

            return self::FAILURE;
        }

        $imgDir = $dir.'/'.($manifest['images_dir'] ?? 'images');
        $files = [];
        foreach ($manifest['floors'] as $floor) {
            if (! empty($floor['overview'])) {
                $files[$floor['overview']] = true;
            }
            foreach ($floor['units'] as $unit) {
                if (! empty($unit['image'])) {
                    $files[$unit['image']] = true;
                }
            }
        }
        $missing = array_filter(array_keys($files), fn ($f) => ! is_file("{$imgDir}/{$f}"));
        if ($missing) {
            $this->error('Missing image files: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $unitCount = array_sum(array_map(fn ($f) => count($f['units']), $manifest['floors']));
        $this->info(sprintf('%s: %d floors, %d apartments, %d unique images.', $building->name, count($manifest['floors']), $unitCount, count($files)));

        if ($this->option('dry-run')) {
            foreach ($manifest['floors'] as $f) {
                $this->line(sprintf('  %-10s %d apartments%s', $f['label'], count($f['units']), empty($f['overview']) ? '  (no overview plan yet)' : ''));
            }
            $this->info('Dry run — nothing uploaded or saved.');

            return self::SUCCESS;
        }

        $cloud = (env('CLOUDINARY_URL') && class_exists(Cloudinary::class)) ? new Cloudinary(env('CLOUDINARY_URL')) : null;
        $this->line($cloud ? 'Uploading to Cloudinary…' : 'Copying to local storage…');

        $stored = [];
        try {
            foreach (array_keys($files) as $name) {
                $path = "{$imgDir}/{$name}";
                if ($cloud) {
                    $res = $cloud->uploadApi()->upload($path, ['folder' => "uaa/floors/buildings/{$slug}", 'resource_type' => 'image']);
                    $stored[$name] = $res['secure_url'];
                } else {
                    $stored[$name] = Storage::disk('public')->putFileAs("floors/buildings/{$slug}", new File($path), $name);
                }
                $this->line("  uploaded {$name}");
            }
        } catch (Throwable $e) {
            $this->error('Upload failed, nothing was saved: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        DB::transaction(function () use ($building, $manifest, $stored) {
            if ($this->option('replace')) {
                $building->planFloors()->delete();
            }

            foreach ($manifest['floors'] as $f) {
                $floor = $building->planFloors()->create([
                    'label' => $f['label'],
                    'sort_order' => $f['sort_order'],
                    'plan_image' => ! empty($f['overview']) ? $stored[$f['overview']] : null,
                    'total_area_sqm' => $f['total_area_sqm'] ?? null,
                    'notes' => $f['notes'] ?? null,
                ]);
                foreach ($f['units'] as $i => $u) {
                    $floor->units()->create([
                        'unit_type' => $u['type'],
                        'bedrooms' => $u['bedrooms'],
                        'suite_sqm' => $u['suite_sqm'],
                        'outdoor_label' => $u['outdoor_label'] ?? 'Balcony',
                        'outdoor_sqm' => $u['outdoor_sqm'],
                        'image' => ! empty($u['image']) ? $stored[$u['image']] : null,
                        'sort_order' => $i,
                    ]);
                }
            }

            $building->update([
                'total_apartments' => $manifest['total_apartments'] ?? null,
                'built_up_sqm' => $manifest['built_up_sqm'] ?? null,
            ]);
        });

        $this->info('Done. Open the building page to see the floor explorer.');

        return self::SUCCESS;
    }
}
