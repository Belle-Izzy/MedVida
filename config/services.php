<?php

return [
    'mercado_pago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
        'return_url' => env('MERCADO_PAGO_RETURN_URL'),
    ],
    'medvida_push' => [
        'subject' => env('MEDVIDA_PUSH_SUBJECT'),
        'public_key' => env('MEDVIDA_PUSH_PUBLIC_KEY'),
        'private_key' => env('MEDVIDA_PUSH_PRIVATE_KEY'),
        'allowed_hosts' => array_filter(array_map('trim', explode(',', env('MEDVIDA_PUSH_ALLOWED_HOSTS', 'fcm.googleapis.com,fcm-xm.googleapis.com,updates.push.services.mozilla.com,web.push.apple.com')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
