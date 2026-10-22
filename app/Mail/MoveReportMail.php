<?php

namespace App\Mail;

use App\Models\MoveReport;
use App\Support\MoveReportFiles;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The inspection report e-mail (replaces the old Gravity Forms "Move-in-out Report"
 * notifications). Everybody gets the report as a PDF attachment plus a private link
 * (valid 60 days). With $withPhotos the inspection photos are attached as well — used
 * for the engineering / web-dev addresses (INSPECTION_PHOTO_EMAILS).
 */
class MoveReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $link;

    public string $pdfLink;

    /** @var list<array{url: string, name: string}> photos that could not be attached (listed as links instead) */
    public array $notAttached = [];

    public int $attachedCount = 0;

    public function __construct(public MoveReport $report, public bool $withPhotos = false, private ?string $pdfBytes = null)
    {
        $this->link = URL::temporarySignedRoute('move-reports.public', now()->addDays(60), ['moveReport' => $report->id]);
        $this->pdfLink = MoveReportFiles::pdfAvailable()
            ? URL::temporarySignedRoute('move-reports.pdf', now()->addDays(60), ['moveReport' => $report->id])
            : $this->link;
    }

    public function build(): self
    {
        $r = $this->report;
        $what = trim($r->type_label.' — '.($r->property_name ?: '').' '.($r->unit_label ?: ''));

        $this->subject("Inspection report {$r->reference}: {$what}");

        $pdf = $this->pdfBytes ?? MoveReportFiles::pdf($r);
        if ($pdf) {
            $this->attachData($pdf, MoveReportFiles::pdfName($r), ['mime' => 'application/pdf']);
        }

        if ($this->withPhotos) {
            $photos = MoveReportFiles::photos($r);
            foreach ($photos['files'] as $f) {
                $this->attachData($f['data'], $f['name'], ['mime' => $f['mime']]);
            }
            $this->attachedCount = count($photos['files']);
            $this->notAttached = $photos['skipped'];
        }

        return $this->view('emails.move-report')->with(['hasPdf' => (bool) $pdf]);
    }
}
