<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Printable move-in / move-out report (browser "Save as PDF"). Admin sign-in required.
Route::get('/admin/move-reports/{moveReport}/print', function (\App\Models\MoveReport $moveReport) {
    return view('move-reports.print', ['r' => $moveReport->load(['items', 'property'])]);
})->middleware(['web', 'auth'])->name('move-reports.print');
