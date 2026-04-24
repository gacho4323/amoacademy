<?php

return [
    'merchant_id' => env('UPC_MERCHANT_ID', '2789001'),
    'terminal_id' => env('UPC_TERMINAL_ID', 'E7051267'),
    'currency_id' => env('UPC_CURRENCY_ID', '941'), // RSD
    'test_mode' => env('UPC_TEST_MODE', true),
    'locale' => env('UPC_LOCALE', 'RS'),

    'gateway_urls' => [
        'test' => 'https://ecg.test.upc.ua/rbrs/enter',
        'production' => 'https://ecommerce.raiffeisenbank.rs/rbrs/enter',
    ],

    'paths' => [
        'private_key' => storage_path('app/upc/') . (env('UPC_MERCHANT_ID', 'default') . '.pem'),
        'public_key' => storage_path('app/upc/') . env('UPC_MERCHANT_ID', 'default') . '.pub',
        'certificate' => storage_path('app/upc/220817055617FFD.crt'),
        //'certificate' => storage_path('app/upc/work-server.CRT'),
        //'certificate' => storage_path('app/upc/test-server.cert'),
        //'certificate' => storage_path('app/upc/1731267.pub'),
    ],

    'callback_urls' => [
        'success' => env('APP_URL') . '/api/payments/execute',
        'failure' => env('APP_URL') . '/api/payments/cancel',
        'notify' => env('APP_URL') . '/api/payments/notify',
    ],
];
