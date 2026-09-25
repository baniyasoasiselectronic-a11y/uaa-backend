<?php

namespace App\Support;

use Filament\Forms\Components\BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * This Filament version ships no built-in image resize/crop step, so admin
 * photo uploads were landing on disk at full camera resolution (some 20MB+)
 * — slow for both the admin panel and the public site. This intercepts the
 * normal upload-save flow and re-encodes the image down to a sane web size
 * immediately after it's stored, with no change to the stored path/filename.
 */
class OptimizesUploadedImages
{
    public static function apply(BaseFileUpload $upload, int $maxWidth = 1920, int $quality = 82): BaseFileUpload
    {
        return $upload->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file) use ($maxWidth, $quality): ?string {
            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (Throwable) {
                return null;
            }

            $storeMethod = $component->getVisibility() === 'public' ? 'storePubliclyAs' : 'storeAs';

            $path = $file->{$storeMethod}(
                $component->getDirectory(),
                $component->getUploadedFileNameForStorage($file),
                $component->getDiskName(),
            );

            if ($path) {
                static::compress($component->getDisk(), $path, $maxWidth, $quality);
            }

            return $path;
        });
    }

    protected static function compress($disk, string $path, int $maxWidth, int $quality): void
    {
        try {
            $fullPath = $disk->path($path);
            if (! is_file($fullPath) || filesize($fullPath) === 0) {
                return;
            }

            // A full-resolution phone photo needs ~(width*height*4) bytes just to
            // decode as a GD truecolor bitmap — easily 50MB+ for a modern camera
            // shot, which can exceed a constrained host's default memory_limit and
            // abort mid-encode. Give this one operation more headroom; restore the
            // previous limit afterwards regardless of outcome.
            $previousLimit = ini_set('memory_limit', '512M');

            try {
                $info = @getimagesize($fullPath);
                if (! $info) {
                    return; // not a recognisable image (e.g. an SVG or PDF) — leave untouched
                }
                $mime = $info['mime'] ?? '';

                // Decide the real decoder from the file's actual content, not its
                // extension — phone/screenshot uploads are sometimes mislabelled.
                $src = match (true) {
                    str_contains($mime, 'jpeg') => @imagecreatefromjpeg($fullPath),
                    str_contains($mime, 'png') => @imagecreatefrompng($fullPath),
                    str_contains($mime, 'webp') => @imagecreatefromwebp($fullPath),
                    default => null,
                };
                if (! $src) {
                    return;
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

                // Encode to a side-by-side temp file first, never straight over the
                // original — imagejpeg()/imagepng() truncate their target the moment
                // they open it, so a mid-encode failure (e.g. hitting memory_limit on
                // a large phone photo) used to leave a 0-byte file behind instead of
                // the original upload. Only swap it in once we know it's real.
                $tmpPath = $fullPath.'.tmp';
                $ok = match (true) {
                    str_contains($mime, 'jpeg') => imagejpeg($src, $tmpPath, $quality),
                    str_contains($mime, 'png') => imagepng($src, $tmpPath, 6),
                    str_contains($mime, 'webp') => imagewebp($src, $tmpPath, $quality),
                    default => false,
                };
                imagedestroy($src);

                if ($ok && is_file($tmpPath) && filesize($tmpPath) > 0) {
                    rename($tmpPath, $fullPath);
                } elseif (is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            } finally {
                ini_set('memory_limit', $previousLimit);
            }
        } catch (Throwable) {
            // Never let a compression hiccup break the upload — the original file just stays as-is.
        }
    }
}
