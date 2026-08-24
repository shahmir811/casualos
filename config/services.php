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

    // VAPID keys authenticating web push notifications for the customer
    // portal PWA. Generated once via `php artisan webpush:vapid`.
    'vapid' => [
        'subject'     => env('VAPID_SUBJECT', 'mailto:casualiteos@gmail.com'),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Optional — Expo's push API works without one, but an access token
    // avoids their stricter unauthenticated rate limit. Generate from the
    // Expo dashboard (Account Settings > Access Tokens) for the project the
    // React Native app is built under.
    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

];
