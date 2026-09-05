<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allows the Next.js frontend (and Sanctum CSRF cookie route) to call the
    | API. Origins are driven by env so production can lock this down.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    // Public content + rate-limited public forms are read by the static site
    // (browser, cross-origin). Token (Bearer) auth needs no cookies, so a
    // wildcard origin is safe here. Lock this down later if desired.
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
