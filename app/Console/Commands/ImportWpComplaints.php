<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Console\Command;
use XMLReader;

/**
 * Imports the old maintenance complaints from the WordPress export file
 * (WordPress admin → Tools → Export → Complaints). The old site stored the details as
 * post meta: complaint_number, REQUISITION_NUMBER, user_name/phone/email, unit_no,
 * complaint_message, availability_date/time_range, payment_status, payment_intent_id…
 * Re-running is safe: records are matched on the WordPress post id.
 */
class ImportWpComplaints extends Command
{
    protected $signature = 'uaa:import-wp-complaints {file : WordPress WXR export of the complaints} {--dry-run}';

    protected $description = 'Import complaint history from the old uaa.ae WordPress site';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error('File not found.');

            return self::FAILURE;
        }

        $xml = new XMLReader;
        $xml->open($file, null, LIBXML_NOCDATA | LIBXML_NONET);
        $n = $updated = $skipped = 0;

        while ($xml->read()) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->name !== 'item') {
                continue;
            }
            $item = simplexml_load_string($xml->readOuterXml(), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            if (! $item) {
                continue;
            }
            $wp = $item->children('http://wordpress.org/export/1.2/');
            if ((string) $wp->post_type !== 'complaints') {
                continue;
            }

            $meta = [];
            foreach ($wp->postmeta as $m) {
                $meta[(string) $m->meta_key] = (string) $m->meta_value;
            }
            $content = $item->children('http://purl.org/rss/1.0/modules/content/');
            $message = trim(strip_tags($meta['complaint_message'] ?? (string) $content->encoded));
            $number = $meta['complaint_number'] ?? $meta['new_complain_number'] ?? null;
            $email = strtolower(trim($meta['user_email'] ?? ''));
            $pay = strtolower($meta['payment_status'] ?? '');
            $when = (string) $wp->post_date;

            $data = [
                'ticket_number' => $number ?: null,
                'complaint_number' => $number ?: null,
                'requisition_number' => $meta['REQUISITION_NUMBER'] ?? null,
                'name' => trim($meta['user_name'] ?? '') ?: 'Tenant (imported)',
                'email' => $email ?: 'unknown@import.invalid',
                'phone' => $meta['user_phone'] ?? null,
                'oracle_property_id' => $meta['property_id'] ?? null,
                'unit_type' => $meta['unit_type'] ?? null,
                'unit_label' => $meta['unit_no'] ?? null,
                'category' => $meta['complaint_type'] ?? null,
                'description' => $message !== '' ? $message : '(no description)',
                'status' => 'closed',
                'priority' => 'medium',
                'visit_date' => $this->date($meta['availability_date'] ?? null),
                'visit_time_range' => $meta['availability_time_range'] ?? null,
                'payment_status' => in_array($pay, ['paid', 'unpaid', 'free'], true) ? $pay : 'not_required',
                'payment_ref' => $meta['payment_intent_id'] ?? null,
            ];

            if ($this->option('dry-run')) {
                $n++;
                continue;
            }

            $id = (int) $wp->post_id;
            $existing = Complaint::where('legacy_wp_id', $id)->first();
            if ($data['ticket_number'] && Complaint::where('ticket_number', $data['ticket_number'])->where('legacy_wp_id', '!=', $id)->exists()) {
                $data['ticket_number'] .= '-'.$id;
            }
            if ($email && ($u = User::where('email', $email)->first())) {
                $data['customer_id'] = $u->id;
            }
            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                $c = new Complaint($data + ['legacy_wp_id' => $id]);
                $c->created_at = $when ?: now();
                $c->updated_at = $when ?: now();
                $c->save();
                $n++;
            }
            if (($n + $updated) % 2000 === 0) {
                $this->line('… '.($n + $updated));
            }
        }

        $this->info(($this->option('dry-run') ? '[dry run] would import ' : 'imported ')."{$n} complaints"
            .($updated ? ", updated {$updated}" : '').($skipped ? ", skipped {$skipped}" : ''));

        return self::SUCCESS;
    }

    private function date(?string $d): ?string
    {
        if (! $d) {
            return null;
        }
        $t = strtotime($d);

        return $t ? date('Y-m-d', $t) : null;
    }
}
