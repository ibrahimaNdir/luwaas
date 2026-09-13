<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commission Luwaas
    |--------------------------------------------------------------------------
    | Pour changer le taux : modifier LUWAAS_COMMISSION_RATE dans .env
    | Exemple : LUWAAS_COMMISSION_RATE=0.08 → 8%
    |           LUWAAS_COMMISSION_MIN=8000  → minimum 8 000 FCFA
    */
    'commission_rate' => (float) env('LUWAAS_COMMISSION_RATE', 0.06), // 6% par défaut
    'commission_min'  => (int)   env('LUWAAS_COMMISSION_MIN',  6000), // 6 000 FCFA minimum

    /*
    |--------------------------------------------------------------------------
    | Gateway de paiement actif
    |--------------------------------------------------------------------------
    | Changer ici (ou dans .env) pour switcher d'agrégateur.
    | La liaison IoC dans AppServiceProvider fait le reste.
    |
    | Valeurs possibles : paydunya | byctorys | cinetpay | ...
    */
    'active_gateway' => env('PAYMENT_GATEWAY', 'paydunya'),

    /*
    |--------------------------------------------------------------------------
    | Payment timeout settings
    |--------------------------------------------------------------------------
    | Configurable expiration times for payment transactions
    */
    'payment_timeout_minutes' => (int) env('PAYMENT_TIMEOUT_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Sandbox specific settings
    |--------------------------------------------------------------------------
    | Overrides for sandbox/testing environment
    */
    'sandbox' => [
        'payment_timeout_minutes' => (int) env('SANDBOX_PAYMENT_TIMEOUT_MINUTES', 2),
    ],

];
