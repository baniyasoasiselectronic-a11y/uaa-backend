<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * One tenant account of the old WordPress site -> a row of `users`. Used by the history import
 * (uaa:import-wp-users) and by the live sync the old site sends (POST /api/sync/user).
 *
 * The old password hash is kept in users.wp_password_hash so the tenant signs in with the password they already
 * have; the first successful sign-in replaces it with a normal hash. Staff-type accounts are not copied
 * (those are created by hand in the admin, with the new, safer roles).
 */
class WpUserSync
{
    public const STAFF_ROLES = ['technician', 'css_js_designer', 'web_designer', 'seo_manager', 'seo_editor', 'editor', 'author', 'contributor', 'uaa_inspector'];

    /** @return array{0:string,1:?User} ['created'|'updated'|'skipped_staff'|'skipped_email'|'dry', user] */
    public static function upsert(array $r, bool $dryRun = false): array
    {
        $email = strtolower(trim((string) ($r['email'] ?? '')));
        $roles = array_values(array_map('strtolower', (array) ($r['roles'] ?? [])));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['skipped_email', null];
        }
        if (array_intersect($roles, self::STAFF_ROLES)) {
            return ['skipped_staff', null];
        }
        // the old site used the Administrator role to make a tenant's complaints free
        $isExempt = in_array('administrator', $roles, true);
        $hash = ! empty($r['password_hash']) ? (string) $r['password_hash'] : null;

        if ($user = User::where('email', $email)->first()) {
            if ($dryRun) {
                return ['dry', $user];
            }
            $fill = [];
            if (! $user->legacy_wp_id && ! empty($r['id'])) {
                $fill['legacy_wp_id'] = $r['id'];
            }
            if (! $user->wp_login && ! empty($r['login'])) {
                $fill['wp_login'] = $r['login'];
            }
            if (! $user->wp_role && $roles) {
                $fill['wp_role'] = $roles[0];
            }
            // The old site is where these tenants sign in, so its newest password hash wins.
            if ($hash && $user->wp_password_hash !== $hash && $user->legacy_wp_id) {
                $fill['wp_password_hash'] = $hash;
            }
            if ($fill) {
                $user->forceFill($fill)->save();
            }

            return ['updated', $user];
        }
        if ($dryRun) {
            return ['dry', null];
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
        $user->forceFill([
            'wp_login' => $r['login'] ?? null,
            'wp_role' => $roles[0] ?? null,
            'wp_password_hash' => $hash,
        ])->save();

        return ['created', $user];
    }
}
