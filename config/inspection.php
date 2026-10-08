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
|   INSPECTION_NOTIFY_EMAILS  office e-mail addresses that get every report (comma-separated)
|   INSPECTION_INSPECTOR_EMAILS  Name=address pairs, e.g. Jaseel=a@uaa.ae,Ranjan Kumar=b@uaa.ae
*/
$list = fn (string $key, string $default) => array_values(array_filter(array_map('trim', explode(',', (string) env($key, $default)))));

return [
    'password' => env('INSPECTION_PASSWORD'),
    'inspectors' => $list('INSPECTION_INSPECTORS', 'Jaseel,Ranjan Kumar'),
    'oracle_url' => rtrim((string) env('INSPECTION_ORACLE_URL', 'https://gazelle-pleasant-anchovy.ngrok-free.app'), '/'),
    'renewal_staff' => $list('RENEWAL_STAFF', 'Yousaf,Lamis'),
    'notify_emails' => $list('INSPECTION_NOTIFY_EMAILS', ''),
    'inspector_emails' => (function () {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', (string) env('INSPECTION_INSPECTOR_EMAILS', '')))) as $pair) {
            if (str_contains($pair, '=')) {
                [$n, $e] = array_map('trim', explode('=', $pair, 2));
                $out[$n] = $e;
            }
        }

        return $out;
    })(),
    'token_hours' => 12,
];
