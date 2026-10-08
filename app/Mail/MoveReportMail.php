<?php

namespace App\Mail;

use App\Models\MoveReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The inspection report e-mail (replaces the old Gravity Forms "Move-in-out Report"
 * notifications). It carries a private link, valid for 60 days, to the printable
 * report — open it and use "Save as PDF" / print.
 */
class MoveReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $link;

    public function __construct(public MoveReport $report)
    {
        $this->link = URL::temporarySignedRoute('move-reports.public', now()->addDays(60), ['moveReport' => $report->id, 'print' => 1]);
    }

    public function build(): self
    {
        $r = $this->report;
        $what = trim($r->type_label.' — '.($r->property_name ?: '').' '.($r->unit_label ?: ''));

        return $this->subject("Inspection report {$r->reference}: {$what}")->view('emails.move-report');
    }
}
