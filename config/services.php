<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // APIs de los compañeros del grupo. Los secretos se definen solo en el entorno.
    'viajes' => [
        'url' => env('VIAJES_API_URL', 'https://agenda-viajes-laes18-api.azurewebsites.net'),
        'client_id' => env('VIAJES_CLIENT_ID'),
        'client_secret' => env('VIAJES_CLIENT_SECRET'),
        'scope' => 'integraciones.completados.read integraciones.viajes.write',
    ],

    'perfiles' => [
        'url' => env('PERFILES_API_URL', 'https://api.proyect.fun/api'),
        'email' => env('PERFILES_ADMIN_EMAIL'),
        'password' => env('PERFILES_ADMIN_PASSWORD'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
