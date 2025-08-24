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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'pesapal' => [
        'base_url'       => env('PESAPAL_BASE_URL', 'cybqa.pesapal.com/pesapalv3'),
        'consumer_key'   => env('PESAPAL_CONSUMER_KEY'),
        'consumer_secret'=> env('PESAPAL_CONSUMER_SECRET'),
        'ipn_id'         => env('PESAPAL_IPN_ID'),
        'callback_url'  => env('PESAPAL_CALLBACK_URL'),
        'webhook_secret'=> env('PESAPAL_WEBHOOK_SECRET', 'demo-secret'),
    ],

    'flutterwave' => [
        'base_url'       => env('FLW_BASE_URL', 'https://api.flutterwave.com'),
        'secret_key'     => env('FLW_SECRET_KEY'),
        'public_key'     => env('FLW_PUBLIC_KEY'),
        'encryption_key' => env('FLW_ENCRYPTION_KEY'),
        'webhook_secret' => env('FLW_WEBHOOK_SECRET'),
    ],

];
