<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Models\User;
use App\Support\WordPressSource;
use Illuminate\Console\Command;
use XMLReader;

/**
 * Imports the old maintenance complaints. The old site stored the details as post meta:
 * complaint_number, REQUISITION_NUMBER, user_name/phone/email, unit_no, complaint_message,
 * availability_date/time_range, payment_status, payment_intent_id…
 *
 *   php artisan uaa:import-wp-complaints --db        reads the old WordPress database directly (recommended)
 *   php artisan uaa:import-wp-complaints file.xml    reads a WordPress export file
 *
 * Re-running is safe: records are matched on the WordPress post id. Nothing is e-mailed.
 */
class ImportWpComplaints extends Command
{
    protected $signature = 'uaa:import-wp-complaints {file? : WordPress WXR export of the complaints (not needed with --db)} {--db : read the old WordPress database (WP_DB_* in .env)} {--dry-run}';

    protected $description = 'Import complaint history from the old uaa.ae WordPress site';

    private int $new = 0;

    private int $updated = 0;

    private int $failed = 0;

    public function handle(): int
    {
        try {
            foreach ($this->option('db') ? WordPressSource::complaints() : $this->fromFile() as $c) {
                try {
                    $this->one($c['id'], (string) $c['date'], (string) $c['content'], $c['meta']);
                } catch (\Throwable $e) {
                    $this->failed++;
                    $this->warn('skipped complaint #'.$c['id'].': '.mb_substr($e->getMessage(), 0, 160));
                }
                if (($this->new + $this->updated) % 2000 === 0 && ($this->new + $this->updated) > 0) {
                    $this->line('… '.($this->new + $this->updated));
                }
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($this->option('dry-run') ? '[dry run] would import ' : 'imported ')."{$this->new} complaints".($this->updated ? ", updated {$this->updated}" : '').($this->failed ? ", skipped {$this->failed} bad records" : ''));

        return self::SUCCESS;
    }

    /** @return iterable<array{id:int,date:string,content:string,meta:array}> */
    private function fromFile(): iterable
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            throw new \RuntimeException('Give the export file, or use --db.');
        }
        $xml = new XMLReader;
        $xml->open($file, null, LIBXML_NOCDATA | LIBXML_NONET);
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
            yield [
                'id' => (int) $wp->post_id,
                'date' => (string) $wp->post_date,
                'content' => (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded,
                'meta' => $meta,
            ];
        }
    }

    private function one(int $id, string $when, string $content, array $meta): void
    {
        $message = trim(strip_tags($meta['complaint_message'] ?? $content));
        $number = $this->token($meta['complaint_number'] ?? $meta['new_complain_number'] ?? null, 60);
        $email = strtolower($this->t($meta['user_email'] ?? '', 250));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $pay = strtolower($this->t($meta['payment_status'] ?? '', 20));

        $data = [
            'ticket_number' => $number ?: null,
            'complaint_number' => $number ?: null,
            'requisition_number' => $this->token($meta['REQUISITION_NUMBER'] ?? null, 60),
            'name' => $this->t($meta['user_name'] ?? '', 250) ?: 'Tenant (imported)',
            'email' => $email ?: 'unknown@import.invalid',
            'phone' => $this->t($meta['user_phone'] ?? '', 250) ?: null,
            'oracle_property_id' => $this->t($meta['property_id'] ?? '', 50) ?: null,
            'unit_type' => $this->t($meta['unit_type'] ?? '', 60) ?: null,
            'unit_label' => $this->t($meta['unit_no'] ?? '', 60) ?: null,
            'category' => $this->t($meta['complaint_type'] ?? '', 250) ?: null,
            'description' => $message !== '' ? $message : '(no description)',
            'status' => 'closed',
            'priority' => 'medium',
            'visit_date' => $this->date($meta['availability_date'] ?? null),
            'visit_time_range' => $this->t($meta['availability_time_range'] ?? '', 60) ?: null,
            'payment_status' => in_array($pay, ['paid', 'unpaid', 'free'], true) ? $pay : 'not_required',
            'payment_ref' => $this->t($meta['payment_intent_id'] ?? '', 250) ?: null,
        ];

        if ($this->option('dry-run')) {
            $this->new++;

            return;
        }
        $existing = Complaint::where('legacy_wp_id', $id)->first();
        if ($data['ticket_number'] && Complaint::where('ticket_number', $data['ticket_number'])->where('legacy_wp_id', '!=', $id)->exists()) {
            $data['ticket_number'] .= '-'.$id;
        }
        if ($email && ($u = User::where('email', $email)->first())) {
            $data['customer_id'] = $u->id;
        }
        if ($existing) {
            $existing->update($data);
            $this->updated++;

            return;
        }
        $c = new Complaint($data + ['legacy_wp_id' => $id]);
        $c->created_at = $when ?: now();
        $c->updated_at = $when ?: now();
        $c->save();
        $this->new++;
    }

    /** Plain text, no tags (the old app sometimes saved a PHP error message), cut to the column size. */
    private function t(mixed $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $v))), 0, $max);
    }

    /** A number/code such as 59444 or BR82558AC: keep the leading code, drop anything after it. */
    private function token(mixed $v, int $max): ?string
    {
        $v = $this->t(preg_replace('/<.*/s', '', (string) $v), 400);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9\-\/_.]*/', $v, $m) ? mb_substr($m[0], 0, $max) : null;
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
