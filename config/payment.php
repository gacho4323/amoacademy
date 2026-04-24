<?php

return [
    'default_gateway' => 'paypal',
    'gateways' => [
        'paypal' => [
            'class' => \App\Services\Gateways\PaypalGateway::class,
        ],
    ],
];