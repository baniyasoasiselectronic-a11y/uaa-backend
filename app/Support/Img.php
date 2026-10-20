<?php

namespace App\Support;

/**
 * Photos are stored on Cloudinary as the full-size originals. Cloudinary can hand out a smaller copy of the same photo
 * on the fly (right size, WebP/AVIF where the browser can show it, automatic quality): about 80% fewer bytes with no
 * visible difference on a website. "c_limit" only ever shrinks, a small photo is never enlarged. Anything that is not a
 * plain Cloudinary photo address is returned unchanged.
 */
class Img
{
    public static function optimize(?string $url, int $width = 1100): ?string
    {
        if (! $url || ! str_contains($url, 'res.cloudinary.com') || ! preg_match('#/image/upload/(v\d+/)#', $url)) {
            return $url;
        }

        return preg_replace('#/image/upload/(v\d+/)#', '/image/upload/f_auto,q_auto,c_limit,w_'.$width.'/$1', $url, 1);
    }
}
