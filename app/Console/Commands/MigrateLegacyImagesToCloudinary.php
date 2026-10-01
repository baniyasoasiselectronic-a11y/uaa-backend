<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\BuildingImage;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\ProjectUpdateImage;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-off backfill for rows created before the Cloudinary migration
 * (fcccb41) that still store a plain local-disk path. Those files live on
 * GoDaddy's app-directory filesystem, which is wiped on every deploy — see
 * CLAUDE.md's note on sqlite not surviving a deploy; the same ephemeral-disk
 * risk applies to any image that never made it to Cloudinary. Cloudinary can
 * fetch directly from a remote URL, so this never touches local disk itself:
 * it resolves each legacy path to its current public URL and hands that to
 * Cloudinary's upload API, then rewrites the DB column to the returned
 * secure_url. Safe to re-run — anything already an http(s) URL is skipped.
 */
class MigrateLegacyImagesToCloudinary extends Command
{
    protected $signature = 'uaa:migrate-legacy-images {--dry-run : List what would change without uploading or saving}';

    protected $description = 'Upload any remaining local-disk images to Cloudinary and rewrite their DB paths';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));

        $jobs = [
            ['model' => Property::class, 'column' => 'main_image', 'folder' => 'properties'],
            ['model' => PropertyImage::class, 'column' => 'path', 'folder' => 'properties'],
            ['model' => Building::class, 'column' => 'main_image', 'folder' => 'buildings'],
            ['model' => BuildingImage::class, 'column' => 'path', 'folder' => 'buildings'],
            ['model' => ProjectUpdateImage::class, 'column' => 'path', 'folder' => 'project-updates'],
        ];

        $migrated = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $job['model'];
            $column = $job['column'];
            $folder = $job['folder'];

            $rows = $modelClass::query()
                ->whereNotNull($column)
                ->where($column, 'not like', 'http%')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $this->info("{$modelClass} ({$column}): {$rows->count()} legacy row(s)");

            foreach ($rows as $row) {
                $localPath = $row->{$column};
                $publicUrl = Str::startsWith($localPath, '/') ? url($localPath) : Storage::url($localPath);

                if ($dryRun) {
                    $this->line("  [dry-run] #{$row->id}: {$localPath} -> would upload {$publicUrl}");
                    continue;
                }

                try {
                    $result = $cloudinary->uploadApi()->upload($publicUrl, [
                        'folder' => "uaa/{$folder}",
                        'resource_type' => 'image',
                    ]);

                    $secureUrl = $result['secure_url'] ?? null;
                    if (! $secureUrl) {
                        throw new \RuntimeException('Cloudinary returned no secure_url');
                    }

                    $row->{$column} = $secureUrl;
                    $row->save();

                    $this->line("  OK #{$row->id}: {$localPath} -> {$secureUrl}");
                    $migrated++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("  FAILED #{$row->id} ({$localPath}): {$e->getMessage()}");
                    report($e);
                }
            }
        }

        $this->info($dryRun
            ? 'Dry run complete — no changes made.'
            : "Done. Migrated: {$migrated}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
