<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Gives a salesperson a login to the admin panel that shows ONLY the Enquiries screen
 * (role enquiries_only — see config/staff_access.php and User::isEnquiriesOnly()).
 *
 *   php artisan uaa:enquiries-user her@uaa.ae --name="Her Name"     create (or add the role to an existing account)
 *   php artisan uaa:enquiries-user her@uaa.ae --reset-password      she forgot / never got the password: set a new one (shown once)
 *   php artisan uaa:enquiries-user her@uaa.ae --remove              take the role away again (she can no longer sign in)
 */
class MakeEnquiriesUser extends Command
{
    protected $signature = 'uaa:enquiries-user {email : The salesperson\'s e-mail address (her login)}
                            {--name= : Her name, for a new account}
                            {--password= : A password for a new account (default: a random one is generated and shown once)}
                            {--reset-password : For an existing enquiries-only account: set a new password (random, shown once) and re-apply the role}
                            {--remove : Remove the enquiries-only role instead}';

    protected $description = 'Create a salesperson login that can only see and answer Enquiries';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (Validator::make(['e' => $email], ['e' => 'required|email'])->fails()) {
            $this->error('That is not a valid e-mail address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($this->option('remove')) {
            if (! $user || ! $user->hasRole('enquiries_only')) {
                $this->warn("{$email} does not have the enquiries-only role.");

                return self::SUCCESS;
            }
            $user->removeRole('enquiries_only');
            $this->info("Removed. {$email} can no longer open the admin panel".($user->hasAnyRole(User::STAFF_ROLES) ? ' as enquiries-only (they still have another staff role).' : '.'));

            return self::SUCCESS;
        }

        Role::findOrCreate('enquiries_only', 'web');
        $password = null;

        if (! $user) {
            $name = trim((string) $this->option('name')) ?: Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@')));
            $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password, 'locale' => 'en']);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->info("Created {$name} <{$email}>.");
        } elseif ($user->hasAnyRole(array_diff(User::STAFF_ROLES, ['enquiries_only']))) {
            $this->error("{$email} already has another staff role, so this role would not limit them. Nothing changed.");

            return self::FAILURE;
        } elseif ($this->option('reset-password')) {
            // only for accounts that are enquiries-only (checked above), so this can never reset an admin's password
            $password = (string) ($this->option('password') ?: Str::password(14, symbols: false));
            $user->forceFill(['password' => $password])->save();
            $this->info("Account {$email} already exists; a NEW password was set and the role is (re)applied.");
        } else {
            $this->info("Account {$email} already exists; only the role is added (password unchanged).");
        }

        $user->assignRole('enquiries_only');
        $this->info('Role: enquiries_only. She can sign in at /admin and sees the Enquiries screen only.');
        if ($password !== null && ! $this->option('password')) {
            $this->line("One-time password: {$password}   (give it to her privately; it is not stored anywhere readable)");
        }

        return self::SUCCESS;
    }
}
