<?php

return [
   'client_id' => env('PAYPAL_CLIENT_ID'),
   'secret' => env('PAYPAL_SECRET'),
   'mode' => env('PAYPAL_MODE', 'sandbox'),
   'currency' => env('PAYPAL_CURRENCY', 'EUR'),
   'settings' => [
       'log_enabled' => true,
       'log_file' => storage_path('logs/paypal.log'),
       'log_level' => 'DEBUG',
   ],
];