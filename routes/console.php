<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// complaints saved while Oracle was unreachable are sent once it is back
Schedule::command('uaa:sync-complaints')->everyFiveMinutes()->withoutOverlapping();
