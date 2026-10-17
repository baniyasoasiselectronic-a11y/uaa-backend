<?php

namespace App\Console\Commands;

use App\Support\WordPressSource;
use App\Support\WpComplaintSync;
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
        [$result] = WpComplaintSync::upsert($id, $when, $content, $meta, 'closed', (bool) $this->option('dry-run'));
        if ($result === 'updated') {
            $this->updated++;
        } else {
            $this->new++;
        }
    }
}
