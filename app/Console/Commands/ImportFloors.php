<?php

namespace App\Console\Commands;

use App\Models\Property;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Loads a project's floors + apartment types from
 * database/data/floors/<slug>/manifest.json (and its images/ folder), so a
 * whole building doesn't have to be typed into the admin by hand. Each unique
 * image is uploaded once and shared by every floor that uses it. Uses
 * Cloudinary when CLOUDINARY_URL is set (production), local disk otherwise.
 */
class ImportFloors extends Command
{
    protected $signature = 'uaa:import-floors {slug : Property slug, e.g. akasya-east}
                            {--replace : Delete the property\'s existing floors first}
                            {--dry-run : Show what would be imported without uploading or saving}
                            {--description-only : Only update the project description from description.md}';

    protected $description = 'Import floors and apartment types for a project from its manifest';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $dir = database_path("data/floors/{$slug}");
        $manifestPath = "{$dir}/manifest.json";

        if (! is_file($manifestPath)) {
            $this->error("No manifest found at {$manifestPath}");

            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $property = Property::where('slug', $slug)->first();
        if (! $property) {
            $this->error("No property with slug '{$slug}'.");

            return self::FAILURE;
        }

        $descPath = "{$dir}/description.md";
        $description = is_file($descPath) ? trim(file_get_contents($descPath)) : null;

        if ($this->option('description-only')) {
            if (! $description) {
                $this->error("No description.md found at {$descPath}");

                return self::FAILURE;
            }
            $property->update(['description' => $description]);
            $this->info("Description updated for {$property->title}.");

            return self::SUCCESS;
        }

        if ($property->floors()->exists() && ! $this->option('replace')) {
            $this->error("'{$slug}' already has floors. Re-run with --replace to rebuild them.");

            return self::FAILURE;
        }

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
        $missing = array_filter(array_keys($files), fn ($f) => ! is_file("{$dir}/images/{$f}"));
        if ($missing) {
            $this->error('Missing image files: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $unitCount = array_sum(array_map(fn ($f) => count($f['units']), $manifest['floors']));
        $this->info(sprintf('%s: %d floors, %d apartments, %d unique images.',
            $property->title, count($manifest['floors']), $unitCount, count($files)));

        if ($this->option('dry-run')) {
            foreach ($manifest['floors'] as $f) {
                $this->line(sprintf('  %-10s %d apartments%s', $f['label'], count($f['units']),
                    empty($f['overview']) ? '  (no overview plan yet)' : ''));
            }
            $this->info('Dry run — nothing uploaded or saved.');

            return self::SUCCESS;
        }

        $cloud = (env('CLOUDINARY_URL') && class_exists(Cloudinary::class)) ? new Cloudinary(env('CLOUDINARY_URL')) : null;
        $this->line($cloud ? 'Uploading to Cloudinary…' : 'Copying to local storage…');

        $stored = [];
        try {
            foreach (array_keys($files) as $name) {
                $path = "{$dir}/images/{$name}";
                if ($cloud) {
                    $res = $cloud->uploadApi()->upload($path, ['folder' => "uaa/floors/{$slug}", 'resource_type' => 'image']);
                    $stored[$name] = $res['secure_url'];
                } else {
                    $stored[$name] = Storage::disk('public')->putFileAs("floors/{$slug}", new File($path), $name);
                }
                $this->line("  uploaded {$name}");
            }
        } catch (Throwable $e) {
            $this->error('Upload failed, nothing was saved: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        DB::transaction(function () use ($property, $manifest, $stored, $description) {
            if ($this->option('replace')) {
                $property->floors()->delete();
            }

            foreach ($manifest['floors'] as $f) {
                $floor = $property->floors()->create([
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

            $property->update([
                'total_apartments' => $manifest['total_apartments'] ?? null,
                'built_up_sqm' => $manifest['built_up_sqm'] ?? null,
                'description' => $description ?: $property->description,
            ]);
        });

        $this->info('Done. Open the project page to see the floor explorer.');

        return self::SUCCESS;
    }
}
