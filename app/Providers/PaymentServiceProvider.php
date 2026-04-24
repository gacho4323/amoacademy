<?php

namespace App\Providers;

use App\Interfaces\PaymentGatewayInterface;
use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentService::class, function ($app) {
            return new PaymentService($app->make(PaymentGatewayInterface::class));
        });

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gatewayKey = request()->input('gateway', config('payment.default_gateway', 'paypal'));
            $gatewayConfig = config("payment.gateways.{$gatewayKey}");

            if (!$gatewayConfig || !isset($gatewayConfig['class']) || !class_exists($gatewayConfig['class'])) {
                throw new \InvalidArgumentException("Payment gateway {$gatewayKey} not found or misconfigured.");
            }

            return $app->make($gatewayConfig['class']);
        });
    }

    public function boot(): void
    {
        //
    }
}