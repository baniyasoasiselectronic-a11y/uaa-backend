<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Support\ComplaintOracle;
use Illuminate\Console\Command;

/** Retries complaints that were saved while Oracle was unreachable, and e-mails the tenant the final number. */
class SyncComplaintsToOracle extends Command
{
    protected $signature = 'uaa:sync-complaints {--limit=30}';

    protected $description = 'Send complaints that are still waiting to Oracle';

    public function handle(): int
    {
        if (! ComplaintOracle::enabled()) {
            $this->info('Posting to Oracle is switched off.');

            return self::SUCCESS;
        }

        $sent = $failed = 0;
        Complaint::query()
            ->where('oracle_status', 'pending')
            ->where('oracle_attempts', '<', 200)
            ->orderBy('id')->limit((int) $this->option('limit'))->get()
            ->each(function (Complaint $c) use (&$sent, &$failed) {
                ComplaintOracle::submit($c) ? $sent++ : $failed++;
            });

        $this->info("Sent to Oracle: {$sent}, still waiting: {$failed}");

        return self::SUCCESS;
    }
}
