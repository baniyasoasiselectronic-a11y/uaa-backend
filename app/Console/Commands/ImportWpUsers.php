<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\WordPressSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Brings the old WordPress tenant accounts over. Passwords cannot be copied (they are
 * hashed differently), so every imported account gets a random password and the tenant
 * sets a new one through "Forgot password". Nothing is e-mailed unless --send-reset is given.
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
            $email = strtolower(trim((string) ($r['email'] ?? '')));
            $roles = array_map('strtolower', (array) ($r['roles'] ?? []));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            // Staff roles are created by hand in the new admin (different, safer roles).
            if (array_intersect($roles, ['technician', 'css_js_designer', 'web_designer', 'seo_manager', 'seo_editor', 'editor', 'author', 'contributor', 'uaa_inspector'])) {
                $skipped++;
                $this->line("skipped staff-type account (create by hand if needed): {$email}");
                continue;
            }
            // The old site used the Administrator role to make a tenant's complaints free.
            $isExempt = in_array('administrator', $roles, true);

            if ($user = User::where('email', $email)->first()) {
                if (! $user->legacy_wp_id && ! $this->option('dry-run')) {
                    $user->update(['legacy_wp_id' => $r['id'] ?? null]);
                }
                $existing++;
                continue;
            }
            if ($this->option('dry-run')) {
                $created++;
                $exempt += $isExempt;
                continue;
            }
            $user = User::create([
                'name' => trim((string) ($r['name'] ?? '')) ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
                'phone' => $r['phone'] ?? null,
                'locale' => 'en',
                'complaint_fee_exempt' => $isExempt,
                'legacy_wp_id' => $r['id'] ?? null,
            ]);
            $created++;
            $exempt += $isExempt;
            if ($this->option('send-reset')) {
                try {
                    Password::sendResetLink(['email' => $user->email]);
                } catch (\Throwable $e) {
                    $this->warn("reset mail failed for {$email}: ".$e->getMessage());
                }
            }
        }

        $this->info(($this->option('dry-run') ? '[dry run] ' : '')."new accounts: {$created} (no-fee flag: {$exempt}), already existed: {$existing}, skipped: {$skipped}");

        return self::SUCCESS;
    }
}
