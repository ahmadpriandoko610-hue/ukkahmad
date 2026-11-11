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

    'whatsapp' => [
        'enabled' => filter_var(env('WHATSAPP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'driver' => env('WHATSAPP_DRIVER', 'webhook'),
        'endpoint' => env('WHATSAPP_API_ENDPOINT'),
        'token' => env('WHATSAPP_API_TOKEN'),
        'auth_header' => env('WHATSAPP_AUTH_HEADER', 'Authorization'),
        'token_prefix' => env('WHATSAPP_TOKEN_PREFIX', 'Bearer'),
        'sender' => env('WHATSAPP_SENDER'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),
    ],

];
