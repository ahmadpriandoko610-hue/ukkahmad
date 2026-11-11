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
        'enabled' => filter_var(env('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'driver' => env('WHATSAPP_DRIVER', 'meta_cloud_api'),
        'dry_run' => filter_var(env('WHATSAPP_DRY_RUN', true), FILTER_VALIDATE_BOOLEAN),
        'require_consent' => filter_var(env('WHATSAPP_REQUIRE_CONSENT', true), FILTER_VALIDATE_BOOLEAN),
        'test_allowlist_only' => filter_var(env('WHATSAPP_TEST_ALLOWLIST_ONLY', true), FILTER_VALIDATE_BOOLEAN),
        'test_allowlist' => array_values(array_filter(array_map(
            static fn (string $number): string => trim($number),
            explode(',', (string) env('WHATSAPP_TEST_ALLOWLIST', ''))
        ), static fn (string $number): bool => $number !== '')),
        'graph_base_url' => rtrim((string) env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'), '/'),
        'graph_version' => trim((string) env('WHATSAPP_GRAPH_VERSION', 'v21.0'), '/'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'template_name' => env('WHATSAPP_TEMPLATE_NAME', 'notifikasi_pemindahan_ruangan'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'id'),
        'connect_timeout' => max(1, (int) env('WHATSAPP_CONNECT_TIMEOUT', 5)),
        'timeout' => max(2, (int) env('WHATSAPP_TIMEOUT', 10)),
    ],

];
