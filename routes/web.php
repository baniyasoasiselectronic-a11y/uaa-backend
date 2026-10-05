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

    return view('move-reports.print', ['r' => $moveReport->load(['items', 'property'])]);
})->middleware('web')->name('move-reports.print');
