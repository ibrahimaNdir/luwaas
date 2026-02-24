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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    
    // PAYPAL (Pour tests et développement)
   
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'), 
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'), 
        'sandbox' => env('PAYPAL_SANDBOX', true), 
        'enabled' => env('PAYPAL_ENABLED', true), 
    ],






    /*

    // ═══════════════════════════════════════════════════════════
    // WAVE (Pour production)
    // ═══════════════════════════════════════════════════════════
    'wave' => [
        'api_key' => env('WAVE_API_KEY'),
        'secret' => env('WAVE_SECRET'),
        'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
        'api_url' => env('WAVE_API_URL', 'https://api.wave.com'),
        'enabled' => env('WAVE_ENABLED', false), // Désactivé par défaut
    ], */



    /*
    // ORANGE MONEY (Pour production)
    

    'orange_money' => [
        'merchant_key' => env('OM_MERCHANT_KEY'),
        'api_username' => env('OM_API_USERNAME'),
        'api_password' => env('OM_API_PASSWORD'),
        'api_url' => env('OM_API_URL', 'https://api.orange.com'),
        'enabled' => env('OM_ENABLED', false), // Désactivé par défaut
    ], */

    /* 
    'free_money' => [
        'api_key' => env('FREE_MONEY_API_KEY'),
        'merchant_id' => env('FREE_MONEY_MERCHANT_ID'),
        'secret' => env('FREE_MONEY_SECRET'),
        'api_url' => env('FREE_MONEY_API_URL', 'https://api.free.sn'),
        'enabled' => env('FREE_MONEY_ENABLED', false), // Désactivé par défaut
    ],*/

    // ═══════════════════════════════════════════════════════════
    // FIREBASE (Notifications et Firestore)
    // ═══════════════════════════════════════════════════════════
    'firebase' => [
        'credentials' => storage_path('app/firebase/ma-cle.json'),
    ],

    // ═══════════════════════════════════════════════════════════
    // FCM (Firebase Cloud Messaging - Legacy)
    // ═══════════════════════════════════════════════════════════
    'fcm' => [
        'key' => env('FCM_SERVER_KEY'),
    ],

];
