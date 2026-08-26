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

    'beside' => [
        'webhook_secret' => env('BESIDE_WEBHOOK_SECRET'),
    ],

    'website_chat' => [
        'webhook_secret' => env('WEBSITE_CHAT_WEBHOOK_SECRET'),
        'notification_email' => env('WEBSITE_CHAT_NOTIFICATION_EMAIL', 'ValorVentureRealty@gmail.com'),
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
        'api_url' => env('GOOGLE_CALENDAR_API_URL', 'https://www.googleapis.com/calendar/v3'),
        'auth_url' => env('GOOGLE_CALENDAR_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => env('GOOGLE_CALENDAR_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'revoke_url' => env('GOOGLE_CALENDAR_REVOKE_URL', 'https://oauth2.googleapis.com/revoke'),
        'scopes' => [
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        ],
        'timeout' => (int) env('GOOGLE_CALENDAR_REQUEST_TIMEOUT', 20),
        'default_duration_minutes' => (int) env('GOOGLE_CALENDAR_DEFAULT_DURATION_MINUTES', 60),
        'inbound_page_size' => (int) env('GOOGLE_CALENDAR_INBOUND_PAGE_SIZE', 2500),
        'inbound_max_pages' => (int) env('GOOGLE_CALENDAR_INBOUND_MAX_PAGES', 10),
    ],

];
