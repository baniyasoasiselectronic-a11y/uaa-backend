<?php

namespace App\Support;

use App\Models\Complaint;
use App\Models\User;

/**
 * Turns one complaint of the old WordPress site (a post of type "complaints" with its post meta) into a row of
 * `complaints`. Used by the history import (uaa:import-wp-complaints) and by the live sync the old site sends
 * (POST /api/sync/complaint), so both always agree. Safe to repeat: rows are matched on the WordPress post id.
 *
 * Staff work is never overwritten: status, priority and assignment are only set when the row is first created.
 */
class WpComplaintSync
{
    /** @return array{0:string,1:?int} ['new'|'updated'|'dry', local id] */
    public static function upsert(int $id, string $when, string $content, array $meta, string $status = 'closed', bool $dryRun = false): array
    {
        $message = trim(strip_tags($meta['complaint_message'] ?? $content));
        $number = self::token($meta['complaint_number'] ?? $meta['new_complain_number'] ?? null, 60);
        $email = strtolower(self::t($meta['user_email'] ?? '', 250));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $pay = strtolower(self::t($meta['payment_status'] ?? '', 20));
        $paid = in_array($pay, ['paid', 'succeeded', 'success'], true);

        $data = [
            'ticket_number' => $number ?: null,
            'complaint_number' => $number ?: null,
            'requisition_number' => self::token($meta['REQUISITION_NUMBER'] ?? null, 60),
            'name' => self::t($meta['user_name'] ?? '', 250) ?: 'Tenant (imported)',
            'email' => $email ?: 'unknown@import.invalid',
            'phone' => self::t($meta['user_phone'] ?? '', 250) ?: null,
            'oracle_property_id' => self::t($meta['property_id'] ?? '', 50) ?: null,
            'unit_type' => self::t($meta['unit_type'] ?? '', 60) ?: null,
            'unit_label' => self::t($meta['unit_no'] ?? '', 60) ?: null,
            'category' => self::t($meta['complaint_type'] ?? '', 250) ?: null,
            'description' => $message !== '' ? $message : '(no description)',
            'visit_date' => self::date($meta['availability_date'] ?? null),
            'visit_time_range' => self::t($meta['availability_time_range'] ?? '', 60) ?: null,
            'payment_status' => $paid ? 'paid' : (in_array($pay, ['unpaid', 'free'], true) ? $pay : ($pay === 'pending' ? 'unpaid' : 'not_required')),
            'payment_ref' => self::t($meta['payment_intent_id'] ?? $meta['payment_id'] ?? '', 250) ?: null,
            'source' => 'wordpress',
            'oracle_status' => 'posted', // the old site only creates a complaint after Oracle accepted it
        ];
        // Stripe amounts are saved in the smallest unit (fils): 10000 = AED 100.00
        $amount = $meta['payment_amount'] ?? null;
        if ($paid && is_numeric($amount) && (float) $amount > 0) {
            $data['payment_amount'] = round((float) $amount / (str_contains((string) $amount, '.') ? 1 : 100), 2);
        }

        if ($dryRun) {
            return ['dry', null];
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

            return ['updated', $existing->id];
        }
        $c = new Complaint($data + ['legacy_wp_id' => $id, 'status' => $status, 'priority' => 'medium']);
        $c->created_at = $when ?: now();
        $c->updated_at = $when ?: now();
        $c->save();

        return ['new', $c->id];
    }

    /** Plain text, no tags (the old app sometimes saved a PHP error message), cut to the column size. */
    public static function t(mixed $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $v))), 0, $max);
    }

    /** A number/code such as 59444 or BR82558AC: keep the leading code, drop anything after it. */
    public static function token(mixed $v, int $max): ?string
    {
        $v = self::t(preg_replace('/<.*/s', '', (string) $v), 400);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9\-\/_.]*/', $v, $m) ? mb_substr($m[0], 0, $max) : null;
    }

    public static function date(?string $d): ?string
    {
        if (! $d) {
            return null;
        }
        $t = strtotime($d);

        return $t ? date('Y-m-d', $t) : null;
    }
}
