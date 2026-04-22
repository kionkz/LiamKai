<?php

return [
    'inventory' => [
        'default_reorder_point' => (int) env('INVENTORY_DEFAULT_REORDER_POINT', 5),
        'default_product_expiration_months' => (int) env('INVENTORY_DEFAULT_PRODUCT_EXPIRATION_MONTHS', 6),
    ],
    'setup' => [
        'seed_admin' => (bool) env('APP_SEED_ADMIN', true),
        'admin_name' => env('APP_DEFAULT_ADMIN_NAME', 'admin'),
        'admin_username' => env('APP_DEFAULT_ADMIN_USERNAME', 'admin'),
        'admin_email' => env('APP_DEFAULT_ADMIN_EMAIL', 'admin@example.com'),
        'admin_password' => env('APP_DEFAULT_ADMIN_PASSWORD', 'myrns123'),
    ],
];