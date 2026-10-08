<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\MoveReportMail;
use App\Models\MoveReport;
use App\Support\InspectionToken;
use App\Support\MoveInspection;
use App\Support\UaaOracle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * API behind the website's Move In / Move Out inspection page (move-in-out).
 * The page asks for the inspection password, receives a short-lived token and
 * then calls ajax() with the same actions the old WordPress plugin used, so the
 * old interface keeps working unchanged.
 */
class InspectionController extends Controller
{
    public function login(Request $r): JsonResponse
    {
        $expected = (string) config('inspection.password');
        if ($expected === '') {
            return response()->json(['success' => false, 'data' => 'The inspection password has not been set up on the server yet.'], 503);
        }
        if (! hash_equals($expected, (string) $r->input('password'))) {
            return response()->json(['success' => false, 'data' => 'The password is not correct.'], 401);
        }

        return response()->json(['success' => true, 'data' => ['token' => InspectionToken::issue()]]);
    }

    public function ajax(Request $r): JsonResponse
    {
        if (! InspectionToken::valid($r->header('X-Inspection-Token'))) {
            return response()->json(['success' => false, 'data' => 'Session expired. Please enter the password again.'], 401);
        }
        $action = $r->input('action', $r->query('action'));

        try {
            return match ($action) {
                'uaa_mio_get_inspectors' => $this->ok(UaaOracle::inspectors()),
                'uaa_mio_get_structure' => $this->ok(array_map(
                    fn ($id, $room) => ['id' => $id, 'name' => $room['name'], 'sections' => $room['sections']],
                    array_keys(MoveInspection::rooms()),
                    array_values(MoveInspection::rooms())
                )),
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
                'uaa_mio_get_tenant' => ($t = UaaOracle::tenant((string) $r->input('property_id'), (string) $r->input('unit_id')))
                    ? $this->ok($t)
                    : response()->json(['success' => false, 'data' => null]),
                'uaa_mio_upload_photo' => $this->upload($r),
                'uaa_mio_save_report' => $this->save($r),
                'uaa_mio_list_reports' => $this->listReports($r),
                'uaa_mio_resend_report' => $this->resend($r),
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
        foreach (['inspection_type' => 'inspection type', 'tenant_name' => 'tenant name', 'unit_no' => 'unit', 'property_id' => 'property', 'inspector' => 'inspector', 'date' => 'date'] as $k => $label) {
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
                'name' => $known[$id]['name'],
                'items' => array_values(array_map(fn ($i) => [
                    'group' => ! empty($i['group']) ? (string) $i['group'] : null,
                    'label' => (string) ($i['label'] ?? ''),
                    'status' => in_array($i['status'] ?? 'ok', ['ok', 'maintenance', 'damaged'], true) ? $i['status'] : 'ok',
                    'notes' => ($i['notes'] ?? '') !== '' ? (string) $i['notes'] : null,
                    'price' => ($i['price'] ?? '') !== '' ? (float) $i['price'] : null,
                    'photos' => array_values(array_filter(array_map(
                        fn ($u) => is_string($u) && $u !== '' ? $this->photoValue($u) : null,
                        (array) ($i['photos'] ?? [])
                    ))),
                ], (array) ($room['items'] ?? []))),
            ];
        }

        $report = MoveReport::create(array_merge([
            'type' => MoveInspection::TYPES[$p['inspection_type']] ?? 'move_in',
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

        $sent = $this->mail($report);

        return $this->ok([
            'report_number' => $report->reference,
            'pdf_url' => URL::temporarySignedRoute('move-reports.public', now()->addDays(60), ['moveReport' => $report->id, 'print' => 1]),
            'emailed_to' => $sent,
        ]);
    }

    /** Tenant + inspector + office addresses for a report (extra address optional). */
    private function recipients(MoveReport $report, ?string $extra = null): array
    {
        $list = array_merge(
            [$report->tenant_email, config('inspection.inspector_emails')[$report->inspector] ?? null, $extra],
            (array) config('inspection.notify_emails')
        );

        return array_values(array_unique(array_map('strtolower', array_filter($list, fn ($e) => is_string($e) && filter_var(trim($e), FILTER_VALIDATE_EMAIL)))));
    }

    /** Sends the report e-mail; a mail problem never stops the report from being saved. */
    private function mail(MoveReport $report, ?string $extra = null): array
    {
        $to = $this->recipients($report, $extra);
        if (! $to) {
            return [];
        }
        try {
            Mail::to($to)->send(new MoveReportMail($report));
            $report->forceFill(['emailed_at' => now()])->save();

            return $to;
        } catch (Throwable $e) {
            Log::error('Inspection report e-mail failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function listReports(Request $r): JsonResponse
    {
        $q = trim((string) $r->input('q'));
        $query = MoveReport::query()->orderByDesc('report_date')->orderByDesc('id');
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('tenant_name', 'like', $like)
                ->orWhere('unit_label', 'like', $like)->orWhere('property_name', 'like', $like)
                ->orWhere('inspector', 'like', $like)->orWhere('tenant_phone', 'like', $like));
        }
        if (in_array($r->input('type'), array_keys(MoveReport::TYPES), true)) {
            $query->where('type', $r->input('type'));
        }
        $page = $query->paginate(15, ['*'], 'page', max(1, (int) $r->input('page', 1)));

        return $this->ok([
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'items' => $page->getCollection()->map(fn (MoveReport $m) => [
                'id' => $m->id,
                'reference' => $m->reference,
                'type' => $m->type,
                'type_label' => $m->type_label,
                'date' => $m->report_date?->format('d M Y'),
                'property' => $m->property_name,
                'unit' => $m->unit_label,
                'tenant' => $m->tenant_name,
                'tenant_email' => $m->tenant_email,
                'inspector' => $m->inspector,
                'total' => (float) $m->total_amount,
                'emailed_at' => $m->emailed_at?->format('d M Y H:i'),
                'imported' => (bool) $m->legacy_entry_id,
                'url' => URL::temporarySignedRoute('move-reports.public', now()->addDays(2), ['moveReport' => $m->id]),
                'print_url' => URL::temporarySignedRoute('move-reports.public', now()->addDays(2), ['moveReport' => $m->id, 'print' => 1]),
            ])->values(),
        ]);
    }

    private function resend(Request $r): JsonResponse
    {
        $report = MoveReport::find($r->input('id'));
        if (! $report) {
            return response()->json(['success' => false, 'data' => 'Report not found.'], 404);
        }
        $extra = trim((string) $r->input('to')) ?: null;
        if ($extra && ! filter_var($extra, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'data' => 'That e-mail address is not valid.'], 422);
        }
        $sent = $this->mail($report, $extra);

        return $sent
            ? $this->ok(['emailed_to' => $sent])
            : response()->json(['success' => false, 'data' => 'No e-mail was sent. Add a tenant e-mail, set INSPECTION_NOTIFY_EMAILS, or type an address.'], 422);
    }
}
