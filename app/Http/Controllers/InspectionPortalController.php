<?php

namespace App\Http\Controllers;

use App\Models\MoveReport;
use App\Support\MoveInspection;
use App\Support\UaaOracle;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Inspector-facing Move In / Move Out wizard (the same screens the old WordPress
 * plugin had): a PIN-protected page for inspectors on site, also open to signed-in
 * admins. The page's script talks to ajax() below with the same actions the old
 * plugin used, so the interface behaves exactly as before.
 */
class InspectionPortalController extends Controller
{
    public const PIN_KEY = 'move_portal_pin_hash';

    private function isAdmin(Request $r): bool
    {
        $u = $r->user();
        try {
            return $u && $u->canAccessPanel(Filament::getPanel('admin'));
        } catch (Throwable) {
            return false;
        }
    }

    private function allowed(Request $r): bool
    {
        return $r->session()->get('move_portal_ok') === true || $this->isAdmin($r);
    }

    public function show(Request $r)
    {
        if (! $this->allowed($r)) {
            return view('inspection.gate', ['hasPin' => (bool) UaaOracle::setting(self::PIN_KEY), 'error' => session('pin_error')]);
        }

        return view('inspection.portal', [
            'inspectors' => UaaOracle::inspectors(),
            'isAdmin' => $this->isAdmin($r),
        ]);
    }

    public function pin(Request $r)
    {
        $key = 'move-pin:'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return redirect('/inspection')->with('pin_error', 'Too many attempts. Please wait a minute and try again.');
        }
        $hash = UaaOracle::setting(self::PIN_KEY);
        if ($hash && Hash::check((string) $r->input('pin'), $hash)) {
            RateLimiter::clear($key);
            $r->session()->regenerate();
            $r->session()->put('move_portal_ok', true);

            return redirect('/inspection');
        }
        RateLimiter::hit($key, 60);

        return redirect('/inspection')->with('pin_error', 'That PIN is not correct.');
    }

    public function ajax(Request $r): JsonResponse
    {
        if (! $this->allowed($r)) {
            return response()->json(['success' => false, 'data' => 'Not signed in. Reload the page and enter the PIN.'], 403);
        }
        $action = $r->input('action', $r->query('action'));

        try {
            return match ($action) {
                'uaa_mio_get_properties' => $this->ok(array_map(
                    fn ($id, $name) => ['id' => (string) $id, 'name' => $name],
                    array_keys(UaaOracle::properties()),
                    array_values(UaaOracle::properties())
                )),
                'uaa_mio_get_unit_types' => $this->ok(array_values(array_map(
                    fn ($l) => ['code' => $l, 'label' => $l],
                    UaaOracle::unitTypes()
                ))),
                'uaa_mio_get_units' => $this->ok(UaaOracle::unitRows((string) $r->input('property_id'))),
                // Tenant details are typed in by the inspector for now.
                'uaa_mio_get_tenant' => response()->json(['success' => false, 'data' => null]),
                'uaa_mio_upload_photo' => $this->upload($r),
                'uaa_mio_save_report' => $this->save($r),
                default => response()->json(['success' => false, 'data' => 'Unknown action'], 400),
            };
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'data' => 'Server error — please try again.'], 500);
        }
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    private function upload(Request $r): JsonResponse
    {
        $r->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:12288']]);
        $file = $r->file('photo');

        if (class_exists(\Cloudinary\Cloudinary::class) && env('CLOUDINARY_URL')) {
            $res = (new \Cloudinary\Cloudinary(env('CLOUDINARY_URL')))->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'uaa/move-reports',
                'resource_type' => 'image',
            ]);
            $url = $res['secure_url'] ?? null;
        } else {
            $path = $file->store('move-reports', 'public');
            $url = Storage::disk('public')->url($path);
        }

        return $url ? $this->ok(['url' => $url]) : response()->json(['success' => false, 'data' => 'Upload failed'], 500);
    }

    /** Stored value for a photo: external URLs as they are, local files as a path on the public disk. */
    private function photoValue(string $url): string
    {
        $prefix = Storage::disk('public')->url('');

        return str_starts_with($url, $prefix) ? substr($url, strlen($prefix)) : $url;
    }

    private function signature(?string $data): ?string
    {
        return is_string($data) && str_starts_with($data, 'data:image/png;base64,') && strlen($data) < 600000 ? $data : null;
    }

    private function save(Request $r): JsonResponse
    {
        $p = $r->json()->all();
        foreach (['tenant_name' => 'tenant name', 'unit_no' => 'unit', 'property_id' => 'property', 'inspector' => 'inspector', 'date' => 'date'] as $k => $label) {
            if (blank($p[$k] ?? null)) {
                return response()->json(['success' => false, 'data' => "Please fill in the {$label}."], 422);
            }
        }

        $known = MoveInspection::rooms();
        $rooms = [];
        foreach ((array) ($p['rooms'] ?? []) as $room) {
            $id = $room['id'] ?? null;
            if (! isset($known[$id])) {
                continue;
            }
            $rooms[$id] = [
                'items' => array_values(array_map(fn ($i) => [
                    'label' => (string) ($i['label'] ?? ''),
                    'status' => in_array($i['status'] ?? 'ok', ['ok', 'maintenance', 'damaged'], true) ? $i['status'] : 'ok',
                    'notes' => ($i['notes'] ?? '') !== '' ? (string) $i['notes'] : null,
                    'price' => ($i['price'] ?? '') !== '' ? (float) $i['price'] : null,
                ], (array) ($room['items'] ?? []))),
                'photos' => array_values(array_filter(array_map(
                    fn ($ph) => ! empty($ph['url']) ? $this->photoValue((string) $ph['url']) : null,
                    (array) ($room['photos'] ?? [])
                ))),
            ];
        }

        $report = MoveReport::create(array_merge([
            'type' => ($p['inspection_type'] ?? '') === 'Move Out' ? 'move_out' : 'move_in',
            'report_date' => $p['date'],
            'contract_no' => $p['contract_no'] ?? null,
            'oracle_property_id' => (string) $p['property_id'],
            'property_name' => $p['property_name'] ?? null,
            'oracle_unit_id' => $p['unit_id'] ?? null,
            'unit_label' => $p['unit_no'],
            'unit_type' => $p['unit_type'] ?? null,
            'beds' => $p['beds'] ?? null,
            'tenant_name' => $p['tenant_name'],
            'tenant_phone' => $p['tenant_phone'] ?? null,
            'tenant_email' => $p['tenant_email'] ?? null,
            'inspector' => $p['inspector'],
            'keys_count' => is_numeric($p['keys'] ?? null) ? (int) $p['keys'] : null,
            'parking_cards' => is_numeric($p['parking_cards'] ?? null) ? (int) $p['parking_cards'] : null,
            'notes' => $p['comments'] ?? null,
            'rooms' => $rooms,
            'tenant_signature' => $this->signature($p['tenant_signature'] ?? null),
            'inspector_signature' => $this->signature($p['inspector_signature'] ?? null),
        ], MoveInspection::totals($rooms)));

        return $this->ok([
            'report_number' => $report->reference,
            'pdf_url' => URL::temporarySignedRoute('move-reports.public', now()->addDays(60), ['moveReport' => $report->id, 'print' => 1]),
        ]);
    }
}
