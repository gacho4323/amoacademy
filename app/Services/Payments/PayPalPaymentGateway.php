<?php

namespace App\Services\Payments;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Course;
use App\Models\User;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Refunds\RefundsCreateRequest;
use Exception;
use Illuminate\Support\Facades\Log;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    private PayPalHttpClient $client;

    public function __construct()
    {
        $clientId = config('paypal.client_id');
        $clientSecret = config('paypal.secret');
        $mode = config('paypal.mode', 'sandbox');

        $environment = $mode === 'live'
            ? new ProductionEnvironment($clientId, $clientSecret)
            : new SandboxEnvironment($clientId, $clientSecret);

        $this->client = new PayPalHttpClient($environment);
    }

    public function createPayment(Course $course, User $user, float $amount): array
    {
        try {
            $formattedAmount = number_format($amount, 2, '.', '');
            $description = substr(preg_replace('/[^\x20-\x7E]/', '', "Purchase of course: " . ($course->title ?? 'Untitled Course')), 0, 127);

            Log::debug('PayPal createPayment Inputs', [
                'amount' => $formattedAmount,
                'currency' => config('paypal.currency', 'EUR'),
                'course_id' => $course->id,
                'description' => $description,
                'user_id' => $user->id,
            ]);

            $request = new OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'course_' . $course->id . '_' . uniqid(),
                        'amount' => [
                            'currency_code' => config('paypal.currency', 'EUR'),
                            'value' => $formattedAmount,
                        ],
                        'description' => $description,
                    ],
                ],
                'application_context' => [
                    'brand_name' => config('app.name', 'AMOAcademy'),
                    'locale' => 'sr-RS',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => config('app.url') . '/api/payments/execute',
                    'cancel_url' => config('app.url') . '/api/payments/cancel',
                    'payment_method' => [
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'preferred_payment_source' => 'card',
                    ],
                ],
            ];

            $response = $this->client->execute($request);

            $approvalUrl = null;
            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') {
                    $approvalUrl = $link->href;
                    break;
                }
            }

            if (!$approvalUrl) {
                throw new Exception('No approval URL found in PayPal response');
            }

            Log::debug('PayPal Payment Created', [
                'payment_id' => $response->result->id,
                'approval_url' => $approvalUrl,
                'return_url' => config('app.url') . '/api/payments/execute',
            ]);

            return [
                'payment_id' => $response->result->id,
                'approval_url' => $approvalUrl,
            ];
        } catch (Exception $e) {
            Log::error('PayPal createPayment Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception("Failed to create PayPal payment: {$e->getMessage()}");
        }
    }

    public function executePayment(string $paymentId, string $payerId): array
    {
        try {
            $request = new OrdersCaptureRequest($paymentId);
            $request->prefer('return=representation');

            $response = $this->client->execute($request);
            $status = $response->result->status === 'COMPLETED' ? 'approved' : 'failed';

            Log::debug('PayPal executePayment Result', [
                'payment_id' => $paymentId,
                'status' => $status,
                'transaction_id' => $response->result->id,
            ]);

            return [
                'status' => $status,
                'transaction_id' => $response->result->id,
            ];
        } catch (Exception $e) {
            Log::error('PayPal executePayment Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception("Failed to execute PayPal payment: {$e->getMessage()}");
        }
    }

    public function refundPayment(string $paymentId, float $amount): array
    {
        try {
            $exchangeRate = config('app.exchange_rate_rsd_to_eur', 117);
            $convertedAmount = $amount / $exchangeRate;
            $formattedAmount = number_format($convertedAmount, 2, '.', '');

            $request = new RefundsCreateRequest($paymentId);
            $request->prefer('return=representation');
            $request->body = [
                'amount' => [
                    'value' => $formattedAmount,
                    'currency_code' => config('paypal.currency', 'EUR'),
                ],
            ];

            $response = $this->client->execute($request);

            $status = $response->result->status === 'COMPLETED' ? 'refunded' : 'failed';

            Log::debug('PayPal refundPayment Result', [
                'payment_id' => $paymentId,
                'status' => $status,
                'refund_id' => $response->result->id,
            ]);

            return [
                'status' => $status,
                'transaction_id' => $response->result->id,
            ];
        } catch (Exception $e) {
            Log::error('PayPal refundPayment Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception("Failed to refund PayPal payment: {$e->getMessage()}");
        }
    }
}