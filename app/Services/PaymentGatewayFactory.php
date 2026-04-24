<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use App\Services\Payments\PayPalPaymentGateway;
use App\Services\Payments\UPCPaymentGateway;
use App\Services\Payments\UplatnicaPaymentGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public static function create(string $gateway): PaymentGatewayInterface
    {
        switch (strtolower($gateway)) {
            case 'paypal':
                return new PayPalPaymentGateway();
            case 'upc':
                return new UPCPaymentGateway();
            case 'uplatnica':
                return new UplatnicaPaymentGateway();
            default:
                throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}");
        }
    }
}