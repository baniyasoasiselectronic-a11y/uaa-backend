<?php

namespace App\Console\Commands;

use App\Models\MoveReport;
use App\Models\TechnicianReport;
use App\Support\MoveInspection;
use App\Support\WordPressSource;
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
    protected $signature = 'uaa:import-gf-entries {file? : CSV/JSON export (not needed with --db)} {--db : read the old WordPress database (WP_DB_* in .env)} {--wp-path= : WordPress folder on this server, used with --db to embed the signature images} {--form=move : move or technician} {--dry-run}';

    protected $description = 'Import old Move in/out or Technician Gravity Forms entries';

    /** radio, description, price, images — in the same order as MoveInspection::rooms() items */
    private const MOVE_FIELDS = [
        'corridor' => [[30, 31, 48, 90], [25, 26, 47, 541], [42, 43, 53, 96], [184, 185, 186, 187]],
        'sitting' => [[140, 143, 141, 542], [158, 159, 160, 161], [170, 171, 172, 543], [174, 175, 191, 544]],
        'bedroom' => [[127, 129, 128, 196], [205, 206, 207, 545], [209, 210, 211, 212], [218, 219, 220, 221], [227, 228, 229, 230], [231, 232, 233, 234]],
        'toilet' => [[133, 153, 152, 154], [235, 236, 237, 238], [247, 248, 249, 251], [260, 261, 262, 263], [264, 265, 266, 267], [277, 278, 279, 280], [285, 286, 287, 288], [289, 290, 291, 292], [293, 294, 295, 296], [297, 298, 299, 300], [306, 307, 308, 309], [314, 315, 316, 317], [322, 323, 324, 325]],
        'kitchen' => [[328, 329, 330, 331], [336, 337, 338, 339], [344, 345, 346, 347], [376, 354, 355, 356], [484, 485, 486, 487], [502, 503, 504, 505], [506, 507, 508, 513], [514, 515, 516, 522]],
    ];

    /** Column order of the Gravity Forms "Export Entries" CSV (field ids, then the entry details). */
    private const CSV_COLUMNS = [
        'move' => '21,18,81,104,528,529,530,30,31,48,90,25,26,47,541,42,43,53,96,184,185,186,187,140,143,141,542,158,159,160,161,170,171,172,543,174,175,191,544,127,129,128,196,205,206,207,545,209,210,211,212,218,219,220,221,227,228,229,230,231,232,233,234,133,153,152,154,235,236,237,238,247,248,249,251,260,261,262,263,264,265,266,267,277,278,279,280,285,286,287,288,289,290,291,292,293,294,295,296,297,298,299,300,306,307,308,309,314,315,316,317,322,323,324,325,328,329,330,331,336,337,338,339,344,345,346,347,376,354,355,356,484,485,486,487,502,503,504,505,506,507,508,513,514,515,516,522,27,28,29,546,548,547,16,46,540,created_by,id,date_created,date_updated,source_url,transaction_id,payment_amount,payment_date,payment_status,post_id,user_agent,ip,pdf',
        'technician' => '3,17,16,18,created_by,id,date_created,date_updated,source_url,transaction_id,payment_amount,payment_date,payment_status,post_id,user_agent,ip,pdf',
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if ($this->option('db')) {
            try {
                $entries = WordPressSource::entries($this->option('form') === 'technician' ? 4 : 3);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        } elseif (preg_match('/\.csv$/i', $file)) {
            $entries = $this->readCsv($file, $this->option('form') === 'technician' ? 'technician' : 'move');
        } else {
            $data = json_decode((string) @file_get_contents($file), true);
            $entries = is_array($data) && isset($data['entries']) ? $data['entries'] : $data;
        }
        if (! is_iterable($entries)) {
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

    /** Turns the exported CSV into the same shape the REST API gives (field id => value). */
    private function readCsv(string $file, string $form): ?array
    {
        $h = @fopen($file, 'r');
        if (! $h) {
            return null;
        }
        $cols = explode(',', self::CSV_COLUMNS[$form]);
        $header = fgetcsv($h);
        if (! $header || count($header) < count($cols) - 1) {
            $this->error('This CSV does not look like the old '.($form === 'move' ? 'Move in-out' : 'Technician').' export (unexpected number of columns).');
            return null;
        }
        $out = [];
        while (($row = fgetcsv($h)) !== false) {
            $e = ['status' => 'active'];
            foreach ($cols as $i => $key) {
                $e[$key] = $row[$i] ?? '';
            }
            $e['date_created'] = $e['date_created'] ?: null;
            if (($e['id'] ?? '') !== '') {
                $out[] = $e;
            }
        }
        fclose($h);

        return $out;
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
                    $cell = (string) $this->v($e, $im);
                    $photos = str_starts_with($cell, '[') ? json_decode($cell, true) : preg_split('/\s*,\s*(?=https?:)/', $cell, -1, PREG_SPLIT_NO_EMPTY);
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
        if (! is_array($list) && is_string($raw)) {
            $list = $this->parseListText($raw);
        }
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

    /** The CSV prints a list field as lines such as "Sr No: 1, Complain No: AB123, ..." — rebuild the rows. */
    private function parseListText(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r?\n/', $text, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $row = [];
            foreach (['Sr No', 'Complain No', 'Spare Parts', 'Status', 'Time-in', 'Time-out', 'Description'] as $label) {
                $row[$label] = preg_match('/'.preg_quote($label, '/').':\s*(.*?)(?=,\s*(?:Sr No|Complain No|Spare Parts|Status|Time-in|Time-out|Description):|$)/s', $line, $m) ? trim($m[1]) : '';
            }
            $rows[] = $row;
        }

        return $rows;
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

        if (str_starts_with($name, 'http')) {
            return $name;
        }
        // Read the signature picture from the old site's folder and store it inside the report,
        // so it still shows after the old site is switched off.
        if ($root = $this->option('wp-path')) {
            $file = rtrim((string) $root, '/\\').'/wp-content/uploads/gravity_forms/signatures/'.basename($name);
            if (is_file($file) && filesize($file) < 400000) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents($file));
            }
        }

        return 'https://uaa.ae/wp-content/uploads/gravity_forms/signatures/'.basename($name);
    }
}
