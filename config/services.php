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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'api_endpoint' => 'https://graph.facebook.com/v22.0',
    ],

    'minimax' => [
        'client_id' => env('MINIMAX_CLIENT_ID'),
        'client_secret' => env('MINIMAX_CLIENT_SECRET'),
        'grant_type' => env('MINIMAX_GRANT_TYPE'),
        'username' => env('MINIMAX_USERNAME'),
        'password' => env('MINIMAX_PASSWORD'),
        'scope' => env('MINIMAX_SCOPE', 'minimax.rs'),
        'locale' => env('MINIMAX_LOCALE', 'RS'),
        'api_url' => env('MINIMAX_API_URL', 'https://moj.minimax.rs/RS/'),
        'organization_id' => env('MINIMAX_ORGANIZATION_ID', '81028'),
        'default_employee_id' => env('MINIMAX_DEFAULT_EMPLOYEE_ID', 408246),
    ],
];
