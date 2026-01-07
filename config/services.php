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

    'hotelbeds' => [
        'api_key' => env('HOTELBEDS_API_KEY'),
        'secret' => env('HOTELBEDS_SECRET'),
        'base_url' => env('HOTELBEDS_BASE_URL', 'https://api.test.hotelbeds.com'),
    ],

    'ownerrez' => [
        'username' => env('OWNERREZ_USERNAME'),
        'password' => env('OWNERREZ_PASSWORD'),
        'base_url' => env('OWNERREZ_BASE_URL', 'https://faststage.ownerrez.com'),
        'environment' => env('OWNERREZ_ENVIRONMENT', 'sandbox'),
    ],

    'authorize_net' => [
        'api_login_id' => env('AUTHORIZE_NET_API_LOGIN_ID'),
        'transaction_key' => env('AUTHORIZE_NET_TRANSACTION_KEY'),
        'base_url' => env('AUTHORIZE_NET_BASE_URL', 'https://apitest.authorize.net/xml/v1/request.api'),
        'environment' => env('AUTHORIZE_NET_ENVIRONMENT', 'sandbox'),
    ],

];
