<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Shareable Move In / Move Out report page (browser "Save as PDF"): opened from the signed link
// the inspection page gives after saving. The link expires and cannot be altered.
Route::get('/move-reports/{moveReport}/view', function (\App\Models\MoveReport $moveReport) {
    return view('move-reports.print', ['r' => $moveReport->load('property')]);
})->middleware(['web', 'signed'])->name('move-reports.public');

// The same report as a real PDF file (needs dompdf; falls back to the printable page without it).
Route::get('/move-reports/{moveReport}/pdf', function (\App\Models\MoveReport $moveReport) {
    $pdf = \App\Support\MoveReportFiles::pdf($moveReport);
    if (! $pdf) {
        return redirect()->to(\Illuminate\Support\Facades\URL::temporarySignedRoute('move-reports.public', now()->addDay(), ['moveReport' => $moveReport->id, 'print' => 1]));
    }

    return response($pdf, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="'.\App\Support\MoveReportFiles::pdfName($moveReport).'"',
    ]);
})->middleware(['web', 'signed'])->name('move-reports.pdf');
