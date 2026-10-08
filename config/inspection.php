<?php

/*
|--------------------------------------------------------------------------
| Move In / Move Out inspection page
|--------------------------------------------------------------------------
| The inspector page lives on the public website (move-in-out) and talks to
| /api/inspection/*. Everything is configured here / in .env — there is no
| admin screen for it.
|
|   INSPECTION_PASSWORD   password inspectors type to open the page (required)
|   INSPECTION_INSPECTORS comma-separated inspector names for the dropdown
|   INSPECTION_ORACLE_URL base URL of the Oracle API (buildings / units / unit types)
|   RENEWAL_STAFF         comma-separated names for "Renewed by" on Contract Renewals
*/
$list = fn (string $key, string $default) => array_values(array_filter(array_map('trim', explode(',', (string) env($key, $default)))));

return [
    'password' => env('INSPECTION_PASSWORD'),
    'inspectors' => $list('INSPECTION_INSPECTORS', 'Jaseel,Ranjan Kumar'),
    'oracle_url' => rtrim((string) env('INSPECTION_ORACLE_URL', 'https://gazelle-pleasant-anchovy.ngrok-free.app'), '/'),
    'renewal_staff' => $list('RENEWAL_STAFF', 'Yousaf,Lamis'),
    'token_hours' => 12,
];
