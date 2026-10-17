<?php

namespace App\Console\Commands;

use App\Support\WordPressSource;
use App\Support\WpUserSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

/**
 * Brings the old WordPress tenant accounts over. With --db each account also keeps its old password hash
 * (users.wp_password_hash), so tenants sign in with the password they already have; the first successful
 * sign-in swaps it for a normal hash. From a JSON file there is no hash, so the account gets a random
 * password and the tenant uses "Forgot password". Nothing is e-mailed unless --send-reset is given.
 * (The live copy of new sign-ups and password changes comes from the old site through /api/sync/user.)
 *
 * Input: the JSON from the WordPress REST users list (id, name, email, roles[]).
 */
class ImportWpUsers extends Command
{
    protected $signature = 'uaa:import-wp-users {file? : JSON export of the WordPress users (not needed with --db)} {--db : read the old WordPress database (WP_DB_* in .env)} {--dry-run} {--send-reset : e-mail every new tenant a password-reset link}';

    protected $description = 'Import tenant accounts from the old uaa.ae WordPress site';

    public function handle(): int
    {
        if ($this->option('db')) {
            try {
                $rows = WordPressSource::users();
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        } else {
            $rows = json_decode((string) @file_get_contents((string) $this->argument('file')), true);
            if (! is_array($rows)) {
                $this->error('Could not read that file as a JSON list of users (or use --db).');

                return self::FAILURE;
            }
        }

        $created = $existing = $skipped = $exempt = 0;
        foreach ($rows as $r) {
            [$result, $user] = WpUserSync::upsert($r, (bool) $this->option('dry-run'));
            if ($result === 'skipped_staff') {
                $skipped++;
                $this->line('skipped staff-type account (create by hand if needed): '.strtolower((string) ($r['email'] ?? '')));
            } elseif ($result === 'skipped_email') {
                $skipped++;
            } elseif ($result === 'updated' || ($result === 'dry' && $user)) {
                $existing++;
            } else { // created, or a dry run for a new account
                $created++;
                $exempt += in_array('administrator', array_map('strtolower', (array) ($r['roles'] ?? [])), true);
                if ($user && $this->option('send-reset')) {
                    try {
                        Password::sendResetLink(['email' => $user->email]);
                    } catch (\Throwable $e) {
                        $this->warn("reset mail failed for {$user->email}: ".$e->getMessage());
                    }
                }
            }
        }

        $this->info(($this->option('dry-run') ? '[dry run] ' : '')."new accounts: {$created} (no-fee flag: {$exempt}), already existed: {$existing}, skipped: {$skipped}");

        return self::SUCCESS;
    }
}
