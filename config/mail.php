<?php

return [

    'default' => env('MAIL_MAILER', 'mailtrap'), // ← corrigé

    'mailers' => [
        'smtp' => [
            'transport'    => 'smtp',
            'host'         => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port'         => env('MAIL_PORT', 587),
            'encryption'   => env('MAIL_ENCRYPTION', 'tls'),
            'username'     => env('MAIL_USERNAME'),
            'password'     => env('MAIL_PASSWORD'),
            'timeout'      => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path'      => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers'   => ['smtp', 'log'],
        ],

        // ✅ Mailtrap (développement)
        'mailtrap' => [
            'transport'  => 'smtp',
            'host'       => env('MAILTRAP_HOST', 'sandbox.smtp.mailtrap.io'),
            'port'       => env('MAILTRAP_PORT', 2525),
            'encryption' => 'tls',
            'username'   => env('MAILTRAP_USERNAME'),
            'password'   => env('MAILTRAP_PASSWORD'),
        ],

        // ✅ Brevo (production)
        'brevo' => [
            'transport'  => 'smtp',
            'host'       => env('BREVO_HOST', 'smtp-relay.brevo.com'),
            'port'       => env('BREVO_PORT', 587),
            'encryption' => 'tls',
            'username'   => env('BREVO_USERNAME'),
            'password'   => env('BREVO_PASSWORD'),
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@luwaas.sn'), // ← corrigé
        'name'    => env('MAIL_FROM_NAME', 'Luwaas'),               // ← corrigé
    ],

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];