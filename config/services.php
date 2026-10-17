<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'places_key' => env('GOOGLE_PLACES_API_KEY'),
        'place_id' => env('GOOGLE_PLACE_ID'),
    ],

    // Where new complaint-form submissions get emailed (mirrors the old
    // uaa.ae WordPress site's "complaints@uaa.ae" WP Mail SMTP setup).
    'complaints' => [
        'notify_email' => env('COMPLAINTS_NOTIFY_EMAIL', 'complaints@uaa.ae'),
        // Send each complaint to UAA's Oracle system, as the old site did (false = only save it here)
        'post_to_oracle' => (bool) env('ORACLE_POST_COMPLAINTS', true),
    ],

    // Shared key the old WordPress site uses to push new complaints / sign-ups / forms here (/api/sync/*).
    // Leave WP_SYNC_KEY empty to keep the sync switched off.
    'wp_sync' => [
        'key' => env('WP_SYNC_KEY'),
    ],

    // Login token for the mobile app (/wp-json/*). Leave JWT_AUTH_SECRET_KEY empty to derive it from APP_KEY.
    'jwt' => [
        'secret' => env('JWT_AUTH_SECRET_KEY'),
    ],

    // Length of the e-mailed code in the app's "forgot password" (the old plugin used 8)
    'password_reset' => [
        'code_length' => (int) env('PASSWORD_RESET_CODE_LENGTH', 8),
    ],

    // Text messages (D7 Networks, same provider as the old site). Nothing is sent until the token is set.
    'd7' => [
        'token' => env('D7_API_TOKEN'),
        'sender' => env('D7_SENDER', 'UAA'),
    ],

];
