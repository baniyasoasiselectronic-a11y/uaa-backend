<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Printable move-in / move-out report (browser "Save as PDF"). Admin sign-in required.
Route::get('/admin/move-reports/{moveReport}/print', function (\App\Models\MoveReport $moveReport) {
    $user = auth()->user();
    if (! $user) {
        return redirect('/admin/login');
    }
    abort_unless($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')), 403);

    return view('move-reports.print', ['r' => $moveReport->load("property")]);
})->middleware('web')->name('move-reports.print');

// Inspector-facing Move In / Out wizard (PIN or admin sign-in) + shareable signed report link.
Route::middleware('web')->group(function () {
    Route::get('/inspection', [\App\Http\Controllers\InspectionPortalController::class, 'show']);
    Route::post('/inspection/pin', [\App\Http\Controllers\InspectionPortalController::class, 'pin'])->middleware('throttle:20,1');
    Route::post('/inspection/ajax', [\App\Http\Controllers\InspectionPortalController::class, 'ajax'])->middleware('throttle:240,1');
});
Route::get('/move-reports/{moveReport}/view', function (\App\Models\MoveReport $moveReport) {
    return view('move-reports.print', ['r' => $moveReport->load('property')]);
})->middleware(['web', 'signed'])->name('move-reports.public');
