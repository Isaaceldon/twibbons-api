<?php
return [

    /*
    |--------------------------------------------------------------------------
    | Free User Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'free' => [
            'campaigns' => 5,
            'ai_generations_per_month' => 3,
            'video_jobs' => 0,
        ],
        'premium' => [
            'campaigns' => -1, // unlimited
            'ai_generations_per_month' => -1,
            'video_jobs' => -1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Settings
    |--------------------------------------------------------------------------
    */
    'images' => [
        'max_upload_size' => 10240, // KB
        'allowed_formats' => ['png', 'jpg', 'jpeg', 'svg'],
        'default_size' => 1080,
        'thumbnail_size' => 300,
        'quality' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Settings
    |--------------------------------------------------------------------------
    */
    'videos' => [
        'max_upload_size' => 51200, // KB (50MB)
        'allowed_formats' => ['mp4', 'webm'],
        'default_duration' => 10, // seconds
        'max_duration' => 30,
        'formats' => [
            '1:1' => ['width' => 1080, 'height' => 1080],
            '9:16' => ['width' => 1080, 'height' => 1920],
            '16:9' => ['width' => 1920, 'height' => 1080],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Generation Settings
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'default_provider' => env('DEFAULT_AI_PROVIDER', 'openai'),
        'default_count' => 4,
        'max_count' => 6,
        'timeout' => 60, // seconds
        'retry_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Statistics Settings
    |--------------------------------------------------------------------------
    */
    'statistics' => [
        'cache_duration' => 3600, // 1 hour
        'aggregate_time' => '02:00', // Run daily at 2 AM
        'retention_days' => 365, // Keep daily stats for 1 year
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Sharing
    |--------------------------------------------------------------------------
    */
    'social' => [
        'platforms' => [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
            'twitter' => 'https://twitter.com/intent/tweet?text={text}&url={url}',
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
            'whatsapp' => 'https://wa.me/?text={text}%20{url}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Settings
    |--------------------------------------------------------------------------
    */
    'qrcode' => [
        'size' => 500,
        'margin' => 2,
        'error_correction' => 'H',
        'format' => 'png',
    ],
];
