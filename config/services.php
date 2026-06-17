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

    'efi' => [
        'client_id' => env('EFI_CLIENT_ID'),
        'client_secret' => env('EFI_CLIENT_SECRET'),
        'sandbox' => filter_var(
            env('EFI_SANDBOX', env('APP_ENV', 'production') !== 'production'),
            FILTER_VALIDATE_BOOLEAN,
        ),
        'pix_key' => env('EFI_PIX_KEY'),
        'certificate' => env('EFI_CERTIFICATE_PATH', ''),
        'certificate_password' => env('EFI_CERTIFICATE_PASSWORD', ''),
        'pix_expiry' => (int) env('EFI_PIX_EXPIRY', 3600),
        'holder_cpf' => env('EFI_HOLDER_CPF', ''),
        'carne_max_value_cents' => (int) env('EFI_CARNE_MAX_VALUE_CENTS', 200_000),
    ],

    'wppconnect' => [
        'base_url' => env('WPPCONNECT_BASE_URL', 'https://wpp.leoontech.com'),
        'session' => env('WPPCONNECT_SESSION', 'sid360'),
        'token' => env('WPPCONNECT_TOKEN', ''),
        'webhook_key' => env('WHATSAPP_WEBHOOK_KEY', ''),
        'timeout' => (int) env('WPPCONNECT_TIMEOUT', 30),
        'media_timeout' => (int) env('WPPCONNECT_MEDIA_TIMEOUT', 90),
    ],

];
