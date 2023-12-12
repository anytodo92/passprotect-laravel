<?php
return [
    'admin_email' => [
        'shawn-passdropit@shawntaylorphoto.com',
        'robinkuipers@hotmail.com',
        'noelshytin18@gmail.com'
    ],
    'user_level' => [
        'normal' => 0,
        'pro' => 1,
        'super' => 2
    ],
    'is_verified' => 1,
    'service_type' => [
        1 => 'dropbox',
        2 => 'google_drive',
        3 => 'notion'
    ],
    'link_type' => [
        1 => 'single',
        2 => 'multiple',
        3 => 'folder'
    ],
    'stripe' => [
        'passdropit_key' => env('STRIPE_PASSDROPIT_KEY'),
        'notions11_key' => env('STRIPE_NOTIONS11_KEY')
    ],
    'site_url' => [
        'passdropit' => 'https://www.passdropit.com',
        'notions11' => 'https://www.notions11.com'
    ],
    'prices' => [
        'upgrade' => 5,
    ],
    'payment_mode' => [
        'balance' => 1,
        'stripe' => 2,
    ],
    'payment_status' => [
        'process' => 0,
        'done' => 1,
    ]
];
