<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Shows how this server sends e-mail (no secrets) and sends one test message, with the real error if it fails. */
class MailTest extends Command
{
    protected $signature = 'uaa:mail-test {to : e-mail address that should receive the test}';

    protected $description = 'Send a test e-mail and show the mail settings in use';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $cfg = (array) config('mail.mailers.'.$mailer);
        $this->line('Mailer:   '.$mailer);
        if ($mailer === 'smtp') {
            $this->line('Server:   '.($cfg['host'] ?? '?').':'.($cfg['port'] ?? '?').'  ('.(($cfg['scheme'] ?? null) ?: 'auto').')');
            $this->line('Login:    '.($cfg['username'] ? preg_replace('/(.).*(@.*)/', '$1***$2', (string) $cfg['username']) : '(none)').'  password: '.(! empty($cfg['password']) ? 'set' : 'NOT SET'));
        }
        $this->line('From:     '.config('mail.from.address').' ('.config('mail.from.name').')');
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is '{$mailer}': nothing is delivered, mail is only written to storage/logs. Set MAIL_MAILER=smtp and the MAIL_* settings in .env, then run php artisan optimize:clear.");
        }

        try {
            Mail::raw("This is a test e-mail from the UAA admin panel.\nSent ".now('Asia/Dubai')->format('d M Y H:i')." (Dubai time).\nIf you can read this, e-mail from the new system works.", function ($m) {
                $m->to($this->argument('to'))->subject('UAA admin panel: test e-mail');
            });
        } catch (\Throwable $e) {
            $this->error('FAILED: '.$e->getMessage());

            return self::FAILURE;
        }
        if ($mailer === 'sendmail') {
            $this->info('Handed to this server mail system. Check the inbox AND the spam folder of '.$this->argument('to').' in the next minute or two.');
        } elseif ($mailer === 'smtp') {
            $this->info('Sent. Check the inbox (and the spam folder) of '.$this->argument('to').'.');
        } else {
            $this->info('Done (see the warning above).');
        }

        return self::SUCCESS;
    }
}
