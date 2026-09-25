<?php

namespace App\Support;

use Cloudinary\Cloudinary;
use Filament\Forms\Components\BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Railway's persistent volume is small and shared by the whole app — it kept
 * filling up from uploaded photos (and, worse, from orphaned Livewire temp
 * files left behind by failed uploads), taking the admin panel down. This
 * sends admin photo uploads straight to Cloudinary instead: nothing image-
 * related touches local disk, and Cloudinary does the resizing/optimizing
 * that OptimizesUploadedImages used to do by hand with GD. The value saved
 * to the DB column is Cloudinary's full secure_url — BuildingResource's and
 * PropertyResource's `url()` helpers already pass absolute URLs through
 * unchanged, so no other code needs to change.
 */
class UploadsToCloudinary
{
    public static function apply(BaseFileUpload $upload, string $folder): BaseFileUpload
    {
        return $upload->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($folder): ?string {
            try {
                if (! $file->exists()) {
                    return null;
                }

                $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));

                $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => "uaa/{$folder}",
                    'resource_type' => 'image',
                    'transformation' => [
                        'width' => 1920,
                        'crop' => 'limit',
                        'quality' => 'auto:good',
                        'fetch_format' => 'auto',
                    ],
                ]);

                return $result['secure_url'] ?? null;
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });
    }
}
