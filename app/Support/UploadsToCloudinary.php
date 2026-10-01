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
    /**
     * Filament's multiple-FileUpload state has occasionally handed relation
     * managers a path wrapped in its own single-item array instead of a
     * plain string — flatten and drop empties so a malformed entry never
     * gets persisted verbatim (seen once in the wild, cause not pinned down).
     *
     * @return list<string>
     */
    public static function normalizePaths(mixed $raw): array
    {
        return array_values(array_filter(array_map(
            fn ($p) => is_array($p) ? (reset($p) ?: null) : $p,
            (array) $raw
        )));
    }

    public static function apply(BaseFileUpload $upload, string $folder): BaseFileUpload
    {
        return $upload
            // Filament's own default getUploadedFileUsing() (see vendor
            // filament/forms BaseFileUpload) checks the stored value against
            // the component's local disk before building a preview URL —
            // which always fails for a Cloudinary URL, leaving the preview
            // blank even though the save succeeded. Short-circuit for our
            // own stored value (an http(s) URL) and hand it back as-is;
            // anything else (older rows that still store a plain relative
            // path from before the Cloudinary migration) falls through to
            // a faithful copy of Filament's own original logic below, so
            // those keep resolving exactly as they always did.
            ->getUploadedFileUsing(function (BaseFileUpload $component, mixed $file, string|array|null $storedFileNames): ?array {
                if (! is_string($file)) {
                    return null;
                }

                $name = $component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames;

                if (str_starts_with($file, 'http')) {
                    return [
                        'name' => $name ?: (basename(parse_url($file, PHP_URL_PATH) ?: '') ?: 'image'),
                        'size' => 0,
                        'type' => null,
                        'url' => $file,
                    ];
                }

                $storage = $component->getDisk();
                $shouldFetchFileInformation = $component->shouldFetchFileInformation();

                if ($shouldFetchFileInformation) {
                    try {
                        if (! $storage->exists($file)) {
                            return null;
                        }
                    } catch (Throwable $exception) {
                        return null;
                    }
                }

                $url = null;
                if ($component->getVisibility() === 'private') {
                    try {
                        $url = $storage->temporaryUrl($file, now()->addMinutes(5));
                    } catch (Throwable $exception) {
                        // This driver does not support creating temporary URLs.
                    }
                }
                $url ??= $storage->url($file);

                return [
                    'name' => $name ?? basename($file),
                    'size' => $shouldFetchFileInformation ? $storage->size($file) : 0,
                    'type' => $shouldFetchFileInformation ? $storage->mimeType($file) : null,
                    'url' => $url,
                ];
            })
            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($folder): ?string {
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
