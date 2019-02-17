<?php

return [
    // The default configuration set.

    'default' => 'default',

    // The connection configuration sets.

    'connections' => [
        'default' => [
            'url' => env('ODOO_API_URL'),
            'port' => env('ODOO_API_PORT', '443'),
            'database' => env('ODOO_API_DATABASE'),
            'username' => env('ODOO_API_USER'),
            'password' => env('ODOO_API_PASSWORD'),

            'model_map' => [
            ],
        ],
    ],

    // Map OpenERP model names to local model classes.

    'model_map' => [
        //'account.invoice' => Foo\Bar\Invoice::class,
    ],
];
