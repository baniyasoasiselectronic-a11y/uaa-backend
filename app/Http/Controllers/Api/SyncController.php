<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\WpComplaintSync;
use App\Support\WpUserSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The old WordPress site pushes every new complaint (with its payment), every new app sign-up / password change
 * and every Gravity Forms entry here, so they show up in this admin panel without the new system ever holding the
 * old database's password. Protected by the shared key in the X-Sync-Key header (VerifySyncKey middleware).
 * Every endpoint takes {"items": [...]} (up to 200) and is safe to repeat: records are matched on the WordPress id.
 */
class SyncController extends Controller
{
    private function items(Request $request): array
    {
        $items = $request->input('items');

        return is_array($items) ? array_slice($items, 0, 200) : [];
    }

    private function done(array $r): JsonResponse
    {
        return response()->json(['ok' => true] + $r);
    }

    /** GET /api/sync/ping: tells the old site the key works, and what is here. */
    public function ping(): JsonResponse
    {
        return $this->done([
            'time' => now()->toIso8601String(),
            'complaints_from_wordpress' => Complaint::where('source', 'wordpress')->count(),
            'users_with_wordpress_login' => User::whereNotNull('legacy_wp_id')->count(),
        ]);
    }

    /** POST /api/sync/complaints  items: [{id, date, content, meta:{...}}] */
    public function complaints(Request $request): JsonResponse
    {
        $new = $updated = $failed = 0;
        foreach ($this->items($request) as $c) {
            try {
                $id = (int) ($c['id'] ?? 0);
                if ($id < 1 || ! is_array($c['meta'] ?? null)) {
                    $failed++;
                    continue;
                }
                // a complaint that has just arrived is open for the team; an old one sent by "send all" is history
                $recent = ($t = strtotime((string) ($c['date'] ?? ''))) && $t >= time() - 3 * 86400;
                [$result] = WpComplaintSync::upsert($id, (string) ($c['date'] ?? ''), (string) ($c['content'] ?? ''), $c['meta'], $recent ? 'new' : 'closed');
                $result === 'new' ? $new++ : $updated++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Sync: complaint failed', ['id' => $c['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }

        return $this->done(compact('new', 'updated', 'failed'));
    }

    /** POST /api/sync/users  items: [{id, login, email, name, roles:[], password_hash}] */
    public function users(Request $request): JsonResponse
    {
        $out = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($this->items($request) as $u) {
            try {
                [$result] = WpUserSync::upsert(is_array($u) ? $u : []);
                if ($result === 'created') {
                    $out['created']++;
                } elseif ($result === 'updated') {
                    $out['updated']++;
                } else {
                    $out['skipped']++;
                }
            } catch (Throwable $e) {
                $out['failed']++;
                Log::warning('Sync: user failed', ['id' => $u['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }

        return $this->done($out);
    }

    /**
     * POST /api/sync/entries  items: [{form_id, entry_id, date, title, fields:{label: value}, entry:{raw Gravity entry}}]
     * Forms 3 (Move in/out) and 4 (Technician daily report) go through the same importer as the history import;
     * any other form becomes an enquiry in the inbox.
     */
    public function entries(Request $request): JsonResponse
    {
        $made = $skipped = $failed = 0;
        foreach ($this->items($request) as $e) {
            try {
                $form = (int) ($e['form_id'] ?? 0);
                $entryId = (string) ($e['entry_id'] ?? ($e['entry']['id'] ?? ''));
                if ($form < 1 || $entryId === '') {
                    $failed++;
                    continue;
                }
                if (in_array($form, [3, 4], true) && is_array($e['entry'] ?? null)) {
                    $entry = $e['entry'] + ['id' => $entryId, 'status' => 'active'];
                    $ok = $this->importGravityEntry($form === 4 ? 'technician' : 'move', $entry, ! empty($e['update']));
                    $ok ? $made++ : $skipped++;
                } else {
                    $this->enquiryFromForm($form, $entryId, $e) ? $made++ : $skipped++;
                }
            } catch (Throwable $ex) {
                $failed++;
                Log::warning('Sync: form entry failed', ['form' => $e['form_id'] ?? null, 'error' => $ex->getMessage()]);
            }
        }

        return $this->done(compact('made', 'skipped', 'failed'));
    }

    /** Runs the existing history importer on a single entry, so the field mapping is exactly the same. */
    private function importGravityEntry(string $form, array $entry, bool $update = false): bool
    {
        $dir = storage_path('app/sync');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/gf-'.Str::random(12).'.json';
        file_put_contents($file, json_encode(['entries' => [$entry]]));
        try {
            Artisan::call('uaa:import-gf-entries', ['file' => $file, '--form' => $form] + ($update ? ['--update' => true] : []));
            $output = Artisan::output();
        } finally {
            @unlink($file);
        }

        return (bool) preg_match('/entries imported:\s*([1-9]\d*)/', $output);
    }

    private function enquiryFromForm(int $formId, string $entryId, array $e): bool
    {
        $fields = is_array($e['fields'] ?? null) ? $e['fields'] : [];
        $name = $email = $phone = null;
        $lines = [];
        foreach ($fields as $label => $value) {
            $value = is_array($value) ? implode(', ', array_filter(array_map('strval', $value))) : (string) $value;
            $value = trim(strip_tags($value));
            if ($value === '') {
                continue;
            }
            $label = Str::limit(trim(strip_tags((string) $label)), 60, '');
            $lines[] = $label.': '.Str::limit($value, 600, '…');
            if (! $email && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $email = $value;
            } elseif (! $phone && preg_match('/phone|mobile|tel|whatsapp/i', $label)) {
                $phone = Str::limit($value, 50, '');
            } elseif (! $name && preg_match('/name/i', $label)) {
                $name = Str::limit($value, 250, '');
            }
        }
        $title = Str::limit(trim(strip_tags((string) ($e['title'] ?? 'Form '.$formId))), 120, '');
        $enquiry = Enquiry::firstOrNew(['session_key' => 'gf-'.$formId.'-'.$entryId, 'source' => 'gravity_form']);
        $isNew = ! $enquiry->exists;
        $enquiry->fill([
            'name' => $name ?: 'Old site form visitor',
            'email' => $email,
            'phone' => $phone,
            'page' => $title,
            'message' => Str::limit('Old website form: '.$title."\n".implode("\n", $lines), 4900, ''),
        ]);
        if ($isNew) {
            $enquiry->status = 'new';
            $when = (string) ($e['date'] ?? '');
            if ($when !== '' && strtotime($when)) {
                $enquiry->created_at = $when;
            }
        }
        $enquiry->save();

        return $isNew;
    }
}
