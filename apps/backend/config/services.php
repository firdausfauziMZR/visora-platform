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

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
    ],

    'ml_service' => [
        'url' => env('ML_SERVICE_URL', 'http://127.0.0.1:8001'),
    ],

    'ml_process' => [
        'python' => env('VISORA_ML_PYTHON', base_path('../ml-service/.venv/bin/python')),
        'script' => env('VISORA_ML_SCRIPT', base_path('../ml-service/visora_ml_cli.py')),
        'timeout' => env('VISORA_ML_TIMEOUT', 60),
        'auto_index_on_upload' => env('VISORA_AUTO_INDEX_ON_UPLOAD', false),
    ],

    'image_variants' => [
        'enabled' => env('VISORA_IMAGE_VARIANTS', true),
        'medium' => [
            'width' => 1280,
            'height' => 1280,
        ],
        'thumbnail' => [
            'width' => 256,
            'height' => 256,
        ],
        'social_og' => [
            'width' => 1200,
            'height' => 630,
        ],
    ],

    'photo_checkout' => [
        'payment_driver' => env('VISORA_PAYMENT_DRIVER', 'local'),
        'price_cents' => env('VISORA_PHOTO_PRICE_CENTS', 1000),
        'currency_code' => env('VISORA_PHOTO_CURRENCY', 'USD'),
        'packages' => [
            'web' => [
                'label' => 'Web Download',
                'description' => 'Compressed copy for sharing and mobile use.',
                'variant' => 'medium',
                'price_cents' => env('VISORA_PHOTO_WEB_PRICE_CENTS', 500),
            ],
            'social' => [
                'label' => 'Social Crop',
                'description' => 'Large social preview crop for posts and previews.',
                'variant' => 'social_og',
                'price_cents' => env('VISORA_PHOTO_SOCIAL_PRICE_CENTS', 800),
            ],
            'original' => [
                'label' => 'Original Download',
                'description' => 'Full original file uploaded by the event organizer.',
                'variant' => 'original',
                'price_cents' => env('VISORA_PHOTO_PRICE_CENTS', 1000),
            ],
        ],
    ],

    'photo_search' => [
        'match_limit' => env('VISORA_MATCH_LIMIT', 20),
        'max_cosine_distance' => env('VISORA_MATCH_MAX_COSINE_DISTANCE', 0.45),
    ],

];
