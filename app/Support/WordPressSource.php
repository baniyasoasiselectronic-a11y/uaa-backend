<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Read-only access to the OLD uaa.ae WordPress database (connection "wordpress",
 * configured with WP_DB_* in .env). Used by the uaa:import-wp-* commands so the old
 * data can be copied straight across on the server, with no export files.
 * Nothing here ever writes to the WordPress database.
 */
class WordPressSource
{
    public static function db(): ConnectionInterface
    {
        if (! config('database.connections.wordpress.database')) {
            throw new \RuntimeException('Set WP_DB_DATABASE, WP_DB_USERNAME and WP_DB_PASSWORD (and WP_DB_HOST if needed) in .env first — the values are in the old site\'s wp-config.php.');
        }

        return DB::connection('wordpress');
    }

    /** Table name with the WordPress prefix, e.g. t('users') → wp_users. */
    public static function t(string $table): string
    {
        return env('WP_DB_PREFIX', 'wp_').$table;
    }

    /** @return iterable<array{id:int,name:string,email:string,roles:array,registered:?string}> */
    public static function users(): iterable
    {
        $db = self::db();
        $cap = self::t('capabilities');
        $last = 0;
        while (true) {
            $rows = $db->table(self::t('users'))->where('ID', '>', $last)->orderBy('ID')->limit(500)->get(['ID', 'user_email', 'display_name', 'user_login', 'user_registered']);
            if ($rows->isEmpty()) {
                return;
            }
            $caps = $db->table(self::t('usermeta'))->whereIn('user_id', $rows->pluck('ID'))->where('meta_key', $cap)->pluck('meta_value', 'user_id');
            foreach ($rows as $r) {
                $roles = @unserialize((string) ($caps[$r->ID] ?? ''), ['allowed_classes' => false]);
                yield [
                    'id' => (int) $r->ID,
                    'name' => $r->display_name ?: $r->user_login,
                    'email' => $r->user_email,
                    'roles' => is_array($roles) ? array_keys(array_filter($roles)) : [],
                    'registered' => $r->user_registered,
                ];
                $last = (int) $r->ID;
            }
        }
    }

    /** @return iterable<array{id:int,date:string,content:string,meta:array}> */
    public static function complaints(): iterable
    {
        $db = self::db();
        $last = 0;
        while (true) {
            $rows = $db->table(self::t('posts'))->where('post_type', 'complaints')->where('post_status', 'publish')->where('ID', '>', $last)
                ->orderBy('ID')->limit(500)->get(['ID', 'post_date', 'post_content']);
            if ($rows->isEmpty()) {
                return;
            }
            $meta = [];
            foreach ($db->table(self::t('postmeta'))->whereIn('post_id', $rows->pluck('ID'))->whereIn('meta_key', [
                'complaint_number', 'new_complain_number', 'REQUISITION_NUMBER', 'user_name', 'user_phone', 'user_email', 'unit_no',
                'unit_type', 'property_id', 'complaint_type', 'complaint_message', 'availability_date', 'availability_time_range',
                'payment_status', 'payment_intent_id',
            ])->get(['post_id', 'meta_key', 'meta_value']) as $m) {
                $meta[$m->post_id][$m->meta_key] = $m->meta_value;
            }
            foreach ($rows as $r) {
                yield ['id' => (int) $r->ID, 'date' => $r->post_date, 'content' => (string) $r->post_content, 'meta' => $meta[$r->ID] ?? []];
                $last = (int) $r->ID;
            }
        }
    }

    /**
     * Gravity Forms entries for a form, shaped like the REST API (field id => value).
     *
     * @return iterable<array>
     */
    public static function entries(int $formId): iterable
    {
        $db = self::db();
        $last = 0;
        while (true) {
            $rows = $db->table(self::t('gf_entry'))->where('form_id', $formId)->where('status', 'active')->where('id', '>', $last)
                ->orderBy('id')->limit(200)->get(['id', 'date_created']);
            if ($rows->isEmpty()) {
                return;
            }
            $meta = [];
            foreach ($db->table(self::t('gf_entry_meta'))->where('form_id', $formId)->whereIn('entry_id', $rows->pluck('id'))->get(['entry_id', 'meta_key', 'meta_value']) as $m) {
                $meta[$m->entry_id][$m->meta_key] = $m->meta_value;
            }
            foreach ($rows as $r) {
                yield ['id' => (string) $r->id, 'status' => 'active', 'date_created' => $r->date_created] + ($meta[$r->id] ?? []);
                $last = (int) $r->id;
            }
        }
    }
}
