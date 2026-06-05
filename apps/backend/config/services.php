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

    'ml_service' => [
        'url' => env('ML_SERVICE_URL', 'http://127.0.0.1:8001'),
    ],

    'ml_process' => [
        'python' => env('VISORA_ML_PYTHON', base_path('../ml-service/.venv/bin/python')),
        'script' => env('VISORA_ML_SCRIPT', base_path('../ml-service/visora_ml_cli.py')),
        'timeout' => env('VISORA_ML_TIMEOUT', 60),
    ],

    'photo_checkout' => [
        'price_cents' => env('VISORA_PHOTO_PRICE_CENTS', 1000),
        'currency_code' => env('VISORA_PHOTO_CURRENCY', 'USD'),
    ],

];
