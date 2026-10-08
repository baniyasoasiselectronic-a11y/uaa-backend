<?php

namespace App\Console\Commands;

use App\Models\MoveReport;
use App\Models\TechnicianReport;
use App\Support\MoveInspection;
use Illuminate\Console\Command;

/**
 * Imports old Gravity Forms entries (Move in-out = form 3, Technician Daily Report = form 4)
 * from a JSON file: either the raw list from the Gravity Forms REST API, or {"entries":[…]}.
 * Field ids below are the ones in the old forms. Re-running is safe (matched on the entry id).
 * Photos and signatures keep pointing at their old uaa.ae URLs, so keep the old site's
 * uploads folder online until they have been copied over.
 */
class ImportGfEntries extends Command
{
    protected $signature = 'uaa:import-gf-entries {file} {--form=move : move or technician} {--dry-run}';

    protected $description = 'Import old Move in/out or Technician Gravity Forms entries';

    /** radio, description, price, images — in the same order as MoveInspection::rooms() items */
    private const MOVE_FIELDS = [
        'corridor' => [[30, 31, 48, 90], [25, 26, 47, 541], [42, 43, 53, 96], [184, 185, 186, 187]],
        'sitting' => [[140, 143, 141, 542], [158, 159, 160, 161], [170, 171, 172, 543], [174, 175, 191, 544]],
        'bedroom' => [[127, 129, 128, 196], [205, 206, 207, 545], [209, 210, 211, 212], [218, 219, 220, 221], [227, 228, 229, 230], [231, 232, 233, 234]],
        'toilet' => [[133, 153, 152, 154], [235, 236, 237, 238], [247, 248, 249, 251], [260, 261, 262, 263], [264, 265, 266, 267], [277, 278, 279, 280], [285, 286, 287, 288], [289, 290, 291, 292], [293, 294, 295, 296], [297, 298, 299, 300], [306, 307, 308, 309], [314, 315, 316, 317], [322, 323, 324, 325]],
        'kitchen' => [[328, 329, 330, 331], [336, 337, 338, 339], [344, 345, 346, 347], [376, 354, 355, 356], [484, 485, 486, 487], [502, 503, 504, 505], [506, 507, 508, 513], [514, 515, 516, 522]],
    ];

    public function handle(): int
    {
        $data = json_decode((string) @file_get_contents($this->argument('file')), true);
        $entries = is_array($data) && isset($data['entries']) ? $data['entries'] : $data;
        if (! is_array($entries)) {
            $this->error('Could not read that file.');

            return self::FAILURE;
        }
        $form = $this->option('form');
        $made = $skipped = 0;
        foreach ($entries as $e) {
            if (! is_array($e) || ! isset($e['id'])) {
                continue;
            }
            if (($e['status'] ?? 'active') !== 'active') {
                $skipped++;
                continue;
            }
            $ok = $form === 'technician' ? $this->technician($e) : $this->move($e);
            $ok ? $made++ : $skipped++;
        }
        $this->info(($this->option('dry-run') ? '[dry run] ' : '')."entries imported: {$made}, skipped (already there or empty): {$skipped}");

        return self::SUCCESS;
    }

    private function move(array $e): bool
    {
        $id = (int) $e['id'];
        if (MoveReport::where('legacy_entry_id', $id)->exists()) {
            return false;
        }
        $typeLabel = $this->v($e, 18) ?: 'Move In';
        $rooms = [];
        foreach (MoveInspection::rooms() as $rid => $room) {
            $items = [];
            $k = 0;
            foreach ($room['sections'] as $sec) {
                foreach ($sec['items'] as $label) {
                    [$st, $ds, $pr, $im] = self::MOVE_FIELDS[$rid][$k++];
                    $price = $this->v($e, $pr);
                    $photos = json_decode((string) $this->v($e, $im), true);
                    $items[] = [
                        'group' => $sec['title'],
                        'label' => $label,
                        'status' => strtolower((string) $this->v($e, $st)) === 'damaged' ? 'damaged' : 'ok',
                        'notes' => $this->v($e, $ds) ?: null,
                        'price' => is_numeric($price) ? (float) $price : null,
                        'photos' => is_array($photos) ? array_values(array_filter($photos, 'is_string')) : [],
                    ];
                }
            }
            $rooms[$rid] = ['name' => $room['name'], 'items' => $items];
        }
        $date = $this->v($e, 21) ?: substr((string) ($e['date_created'] ?? ''), 0, 10);

        if ($this->option('dry-run')) {
            return true;
        }
        $r = new MoveReport(array_merge([
            'legacy_entry_id' => $id,
            'type' => MoveInspection::TYPES[$typeLabel] ?? 'move_in',
            'report_date' => $date ?: now()->toDateString(),
            'beds' => $this->v($e, 81),
            'tenant_name' => $this->v($e, 104) ?: 'Tenant',
            'tenant_phone' => $this->v($e, 548),
            'tenant_email' => $this->v($e, 547),
            'property_name' => $this->v($e, 528),
            'unit_type' => $this->v($e, 529),
            'unit_label' => $this->v($e, 530),
            'inspector' => $this->v($e, 46) ?: 'Inspector',
            'keys_count' => is_numeric($this->v($e, 27)) ? (int) $this->v($e, 27) : null,
            'parking_cards' => is_numeric($this->v($e, 28)) ? (int) $this->v($e, 28) : null,
            'notes' => $this->v($e, 546),
            'rooms' => $rooms,
            'tenant_signature' => $this->signature($this->v($e, 16)),
            'inspector_signature' => $this->signature($this->v($e, 540)),
        ], MoveInspection::totals($rooms)));
        $r->created_at = $e['date_created'] ?? now();
        $r->updated_at = $e['date_created'] ?? now();
        $r->save();

        return true;
    }

    private function technician(array $e): bool
    {
        $id = (int) $e['id'];
        if (TechnicianReport::where('legacy_entry_id', $id)->exists()) {
            return false;
        }
        $raw = $this->v($e, 18);
        $list = is_string($raw) ? (@unserialize($raw, ['allowed_classes' => false]) ?: json_decode($raw, true)) : $raw;
        $entries = [];
        foreach ((array) $list as $row) {
            $row = array_values((array) $row);
            $entries[] = [
                'complain_no' => $row[1] ?? '',
                'spare_parts' => $row[2] ?? '',
                'status' => in_array($row[3] ?? '', ['Done', 'Pending', 'In progress'], true) ? $row[3] : 'Done',
                'time_in' => $row[4] ?? null,
                'time_out' => $row[5] ?? null,
                'description' => $row[6] ?? '',
            ];
        }
        if ($this->option('dry-run')) {
            return true;
        }
        $t = new TechnicianReport([
            'legacy_entry_id' => $id,
            'technician_name' => $this->v($e, 3) ?: 'Technician',
            'technician_code' => $this->v($e, 17),
            'report_date' => $this->v($e, 16) ?: substr((string) ($e['date_created'] ?? now()), 0, 10),
            'entries' => $entries,
            'imported' => true,
        ]);
        $t->created_at = $e['date_created'] ?? now();
        $t->save();

        return true;
    }

    private function v(array $e, int $field): ?string
    {
        $x = $e[(string) $field] ?? null;

        return is_string($x) && trim($x) !== '' ? trim($x) : null;
    }

    /** Gravity Forms stores only the signature file name. */
    private function signature(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return str_starts_with($name, 'http') ? $name : 'https://uaa.ae/wp-content/uploads/gravity_forms/signatures/'.$name;
    }
}
