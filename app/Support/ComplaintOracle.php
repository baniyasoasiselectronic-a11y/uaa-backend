<?php

namespace App\Support;

use App\Models\Complaint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Hands a saved complaint to Oracle and, when Oracle accepts it, replaces the temporary ticket
 * with the real complaint number (building code + requisition + type code) and tells the tenant.
 *
 * complaints.oracle_status:  posted  = Oracle has it
 *                            pending = saved here, Oracle not reachable yet (retried by uaa:sync-complaints)
 *                            manual  = no building / unit / type from Oracle, someone enters it in Oracle by hand
 */
class ComplaintOracle
{
    public static function enabled(): bool
    {
        return (bool) config('services.complaints.post_to_oracle', true);
    }

    public static function canPost(Complaint $c): bool
    {
        return filled($c->oracle_property_id) && filled($c->oracle_unit_id) && filled($c->complaint_type_code);
    }

    /** Try to post; returns true when Oracle now has the complaint. */
    public static function submit(Complaint $c, bool $notifyTenant = true): bool
    {
        if ($c->oracle_status === 'posted') {
            return true;
        }
        if (! self::enabled()) {
            $c->forceFill(['oracle_status' => 'manual', 'oracle_last_error' => 'Posting to Oracle is switched off'])->save();

            return false;
        }
        if (! self::canPost($c)) {
            $c->forceFill(['oracle_status' => 'manual', 'oracle_last_error' => 'No building / unit / type from Oracle was chosen'])->save();

            return false;
        }

        $error = null;
        // Same body the old site sent. Oracle expects payment_status "Paid" even for free users (they get a FREE- intent id).
        $requisition = UaaOracle::postComplaint([
            'property_id' => (int) $c->oracle_property_id,
            'unit_id' => $c->oracle_unit_id,
            'caller_name' => $c->name,
            'phone_number' => $c->phone,
            'mobile' => $c->phone,
            'complaint_type' => $c->complaint_type_code,
            'complaint_details' => $c->description,
            'email' => $c->email,
            'status' => 'N',
            'comments' => $c->description,
            'org_id' => 1,
            'unit_type' => $c->unit_type_code,
            'tenant_avl_date' => $c->visit_date?->format('Y-m-d') ?? '',
            'tenant_avl_time' => $c->visit_time_range ?? '',
            'payment_status' => 'Paid',
            'payment_intent_id' => $c->payment_ref ?: 'FREE-'.time(),
        ], $error);

        if ($requisition === null) {
            $c->forceFill([
                'oracle_status' => 'pending',
                'oracle_attempts' => min(250, (int) $c->oracle_attempts + 1),
                'oracle_last_error' => mb_substr((string) $error, 0, 250),
            ])->save();

            return false;
        }

        $number = ComplaintNumber::make($c->oracle_property_id, $c->complaint_type_code, $requisition);
        $c->forceFill([
            'oracle_status' => 'posted',
            'oracle_attempts' => min(250, (int) $c->oracle_attempts + 1),
            'oracle_last_error' => null,
            'requisition_number' => $requisition,
            'complaint_number' => $number,
            'ticket_number' => $number,
        ]);
        try {
            $c->save();
        } catch (UniqueConstraintViolationException) {
            // that ticket number is already used by another row: keep this one's own ticket, Oracle still has it
            $c->ticket_number = $c->getOriginal('ticket_number');
            $c->save();
        }

        if ($notifyTenant) {
            self::notifyTenant($c);
        }

        return true;
    }

    /** Confirmation e-mail + SMS with the real complaint number (same wording as the old site). */
    public static function notifyTenant(Complaint $c): void
    {
        $number = $c->complaint_number ?: $c->ticket_number;
        $when = now('Asia/Dubai')->format('d-m-Y h:i:s A');

        if (filter_var($c->email, FILTER_VALIDATE_EMAIL)) {
            $text = "Greetings,\n\nYour complaint has been received. The maintenance team will visit your place soon to fix the issue.\n"
                ."Your complaint number is {$number}.\nSubmitted on: {$when}\n\n"
                ."If you have any queries or need further assistance, feel free to contact us on WhatsApp:\nhttps://wa.me/97142354004\n\n"
                ."Kind Regards,\nHelp Desk\nMAINTENANCE DEPARTMENT\nUNITED ARAB AGENCIES";
            try {
                Mail::raw($text, fn ($m) => $m->to($c->email)->subject('Complaint Received - Complaint #'.$number));
            } catch (Throwable $e) {
                Log::warning('Complaint confirmation e-mail failed', ['complaint' => $c->id, 'error' => $e->getMessage()]);
            }
        }

        D7Sms::send($c->phone, "Greetings from UAA! Your Complaint Number is {$number} at {$when}. For queries, contact us on WhatsApp: https://wa.me/97142354004");
    }
}
