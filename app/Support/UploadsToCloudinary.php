<?php

namespace App\Support;

use Cloudinary\Cloudinary;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Railway's persistent volume is small and shared by the whole app — it kept
 * filling up from uploaded photos (and, worse, from orphaned Livewire temp
 * files left behind by failed uploads), taking the admin panel down. This
 * sends admin photo uploads straight to Cloudinary instead: nothing image-
 * related touches local disk. The value saved to the DB column is
 * Cloudinary's full secure_url — BuildingResource's and PropertyResource's
 * `url()` helpers already pass absolute URLs through unchanged, so no other
 * code needs to change.
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

                $uploadPath = static::shrinkForUpload($file->getRealPath());

                $cloudinary = new Cloudinary(env('CLOUDINARY_URL'));

                $result = $cloudinary->uploadApi()->upload($uploadPath, [
                    'folder' => "uaa/{$folder}",
                    'resource_type' => 'image',
                ]);

                if ($uploadPath !== $file->getRealPath() && is_file($uploadPath)) {
                    @unlink($uploadPath);
                }

                return $result['secure_url'] ?? null;
            } catch (Throwable $e) {
                report($e);

                Notification::make()
                    ->title('Photo upload failed')
                    ->body(static::friendlyMessage($e))
                    ->danger()
                    ->send();

                return null;
            }
        });
    }

    /**
     * Cloudinary's free plan rejects any single image over 10MB — well
     * within what a modern phone camera produces (routinely 15-30MB+).
     * Resize/re-encode locally with GD before it ever leaves this server,
     * so uploads never hit that ceiling in the first place. Returns the
     * original path unchanged if it's already small or isn't a format GD
     * can decode (e.g. HEIC) — Cloudinary can still accept those directly,
     * just without this pre-shrink.
     */
    protected static function shrinkForUpload(string $path, int $maxWidth = 1920, int $quality = 82): string
    {
        if (filesize($path) < 8 * 1024 * 1024) {
            return $path; // already comfortably under Cloudinary's 10MB cap
        }

        $info = @getimagesize($path);
        if (! $info) {
            return $path;
        }
        $mime = $info['mime'] ?? '';

        $src = match (true) {
            str_contains($mime, 'jpeg') => @imagecreatefromjpeg($path),
            str_contains($mime, 'png') => @imagecreatefrompng($path),
            str_contains($mime, 'webp') => @imagecreatefromwebp($path),
            default => null,
        };
        if (! $src) {
            return $path;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int) round($h * ($maxWidth / $w));
            $dst = imagecreatetruecolor($newW, $newH);
            if (str_contains($mime, 'png')) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        $tmpPath = $path.'.shrunk.jpg';
        $ok = imagejpeg($src, $tmpPath, $quality);
        imagedestroy($src);

        return ($ok && is_file($tmpPath) && filesize($tmpPath) > 0) ? $tmpPath : $path;
    }

    protected static function friendlyMessage(Throwable $e): string
    {
        if (str_contains($e->getMessage(), 'too large')) {
            return 'That photo is too large even after compressing it. Try a smaller/lower-resolution image.';
        }

        return 'Could not reach Cloudinary. Check your connection and try again.';
    }
}
