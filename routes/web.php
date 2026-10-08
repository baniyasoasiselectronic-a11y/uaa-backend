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
