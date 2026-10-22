<?php

namespace App\Support;

use App\Models\MoveReport;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The files that go with a Move In / Move Out report e-mail: the report as a real PDF
 * (dompdf — optional, the app still works without it) and the inspection photos.
 */
class MoveReportFiles
{
    /** Attachments stay well under the usual 20–25 MB mailbox limit. */
    public const MAX_PHOTO_BYTES = 15 * 1024 * 1024;

    public const MAX_PHOTOS = 40;

    public static function pdfAvailable(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    /** "Building - Unit - Move Out Report": what people see when they download, save or forward the PDF. */
    public static function baseName(MoveReport $r): string
    {
        $parts = array_filter([
            trim((string) ($r->property_name ?: $r->property?->title)),
            trim((string) $r->unit_label),
            $r->type_label.' Report',
        ], fn ($p) => $p !== '');
        // no characters that file systems or mail headers dislike
        $name = trim(preg_replace('/\s+/', ' ', preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', implode(' - ', $parts))));

        return $name !== '' ? $name : $r->reference;
    }

    public static function pdfName(MoveReport $r): string
    {
        return self::baseName($r).'.pdf';
    }

    /** The report as PDF bytes, or null when dompdf is missing or rendering failed. */
    public static function pdf(MoveReport $r): ?string
    {
        if (! self::pdfAvailable()) {
            return null;
        }
        try {
            $dir = storage_path('app/dompdf');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $html = view('move-reports.print', ['r' => $r->loadMissing('property'), 'pdf' => true])->render();
            $dompdf = new \Dompdf\Dompdf([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'fontCache' => $dir,
                'tempDir' => $dir,
                'chroot' => base_path(),
            ]);
            $dompdf->setPaper('A4');
            $dompdf->loadHtml($html);
            $dompdf->render();

            return $dompdf->output();
        } catch (Throwable $e) {
            Log::error('Inspection report PDF failed', ['report' => $r->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Every photo of the report in walk-through order: [['url' => …, 'name' => …], …]. */
    public static function photoList(MoveReport $r): array
    {
        $out = [];
        $rooms = (array) $r->rooms;
        $order = array_values(array_unique(array_merge(array_keys(MoveInspection::rooms()), array_keys($rooms))));
        foreach ($order as $rid) {
            $room = $rooms[$rid] ?? null;
            if (! is_array($room)) {
                continue;
            }
            foreach ((array) ($room['items'] ?? []) as $it) {
                foreach (MoveReport::photoUrls($it['photos'] ?? []) as $n => $url) {
                    $out[] = ['url' => $url, 'name' => self::photoName($r, $room['name'] ?? $rid, $it['label'] ?? '', $n + 1, $url)];
                }
            }
            foreach (MoveReport::photoUrls($room['photos'] ?? []) as $n => $url) {
                $out[] = ['url' => $url, 'name' => self::photoName($r, $room['name'] ?? $rid, 'room', $n + 1, $url)];
            }
        }

        // numbered so two items with the same label never produce the same file name
        foreach ($out as $i => $p) {
            $out[$i]['name'] = sprintf('%02d_%s', $i + 1, $p['name']);
        }

        return $out;
    }

    /**
     * Downloads the photos for attaching. Returns the files that fit and the ones left out
     * (they stay available through the links in the report and the e-mail).
     *
     * @return array{files: list<array{name: string, data: string, mime: string}>, skipped: list<array{url: string, name: string}>}
     */
    public static function photos(MoveReport $r): array
    {
        $list = self::photoList($r);
        $files = [];
        $skipped = [];
        $total = 0;

        try {
            $responses = Http::pool(function (Pool $pool) use ($list) {
                return array_map(
                    fn ($p, $i) => $pool->as((string) $i)->timeout(20)->get(self::mailSized($p['url'])),
                    $list,
                    array_keys($list)
                );
            });
        } catch (Throwable $e) {
            Log::error('Inspection report photo download failed', ['report' => $r->id, 'error' => $e->getMessage()]);

            return ['files' => [], 'skipped' => $list];
        }

        foreach ($list as $i => $p) {
            $res = $responses[(string) $i] ?? null;
            $ok = $res instanceof \Illuminate\Http\Client\Response && $res->successful() && $res->body() !== '';
            if (! $ok) {
                // the mail-sized copy can be refused or still be generating — try the original photo once
                try {
                    $second = Http::timeout(30)->get($p['url']);
                    if ($second->successful() && $second->body() !== '') {
                        $res = $second;
                        $ok = true;
                    }
                } catch (Throwable $e) {
                    // logged below
                }
            }
            if (! $ok) {
                Log::warning('Inspection report photo could not be fetched', [
                    'report' => $r->id,
                    'url' => $p['url'],
                    'status' => $res instanceof \Illuminate\Http\Client\Response ? $res->status() : 'no response',
                ]);
            }
            $size = $ok ? strlen($res->body()) : 0;
            if (! $ok || count($files) >= self::MAX_PHOTOS || $total + $size > self::MAX_PHOTO_BYTES) {
                $skipped[] = $p;

                continue;
            }
            $mime = strtolower(trim(explode(';', (string) $res->header('Content-Type'))[0])) ?: 'image/jpeg';
            $files[] = ['name' => $p['name'], 'data' => $res->body(), 'mime' => str_starts_with($mime, 'image/') ? $mime : 'image/jpeg'];
            $total += $size;
        }

        return ['files' => $files, 'skipped' => $skipped];
    }

    /** Cloudinary photos straight from a phone can be 5–10 MB; ask for a mail-sized copy (max 1600 px). */
    private static function mailSized(string $url): string
    {
        return str_contains($url, 'res.cloudinary.com') && str_contains($url, '/image/upload/') && ! str_contains($url, '/upload/w_')
            ? str_replace('/image/upload/', '/image/upload/w_1600,c_limit,q_auto:good/', $url)
            : $url;
    }

    private static function photoName(MoveReport $r, string $room, string $item, int $n, string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';

        return $r->reference.'_'.Str::slug($room).'_'.Str::slug($item ?: 'photo').'_'.$n.'.'.$ext;
    }
}
