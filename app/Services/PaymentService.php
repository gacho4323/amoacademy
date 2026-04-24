<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private PaymentGatewayInterface $gateway;

    public function __construct(string $gateway)
    {
        $this->gateway = PaymentGatewayFactory::create($gateway);
    }

    public function processPayment(Course $course, User $user, string $gateway): array
    {
        return DB::transaction(function () use ($course, $user, $gateway) {
            try {
                $amount = $gateway === 'paypal' ? $course->price / config('app.exchange_rate_rsd_to_eur', 117) : $course->price;
                $currency = $gateway === 'paypal' ? config('paypal.currency', 'EUR') : config('upc.currency_id', '941');

                $paymentData = $this->gateway->createPayment($course, $user, $amount);

                // Check for existing payment_id to avoid duplicates
                if (Payment::where('payment_id', $paymentData['payment_id'])->exists()) {
                    Log::warning('Duplicate payment_id in PaymentService', [
                        'payment_id' => $paymentData['payment_id'],
                    ]);
                    throw new Exception('Payment ID already exists: ' . $paymentData['payment_id']);
                }

                Log::debug('Creating payment record', [
                    'payment_id' => $paymentData['payment_id'],
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ]);

                $payment = Payment::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'amount' => $course->price,
                    'currency' => config('app.currency', 'RSD'),
                    'converted_amount' => $amount,
                    'converted_currency' => $currency,
                    'payment_gateway' => $gateway,
                    'payment_id' => $paymentData['payment_id'],
                    'status' => 'pending', // Always pending initially
                    'token' => $user->currentAccessToken()->plainTextToken,
                ]);

                Log::info('Payment record created', [
                    'payment_id' => $payment->payment_id,
                    'database_id' => $payment->id,
                    'gateway' => $gateway,
                ]);

                return [
                    'payment' => $payment,
                    'redirect_url' => $paymentData['approval_url'],
                    'form_data' => $paymentData['form_data'] ?? null,
                ];
            } catch (Exception $e) {
                Log::error('Payment processing failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new Exception("Payment processing failed for {$gateway}: {$e->getMessage()}");
            }
        });
    }

    public function completePayment(string $paymentId, string $transactionId): Payment
    {
        return DB::transaction(function () use ($paymentId, $transactionId) {
            try {
                $payment = Payment::where('payment_id', $paymentId)->firstOrFail();
                $result = $this->gateway->executePayment($paymentId, $transactionId);

                if (!isset($result['status']) || !isset($result['transaction_id'])) {
                    Log::error('Invalid executePayment result', ['result' => $result]);
                    throw new Exception('Invalid payment execution result');
                }

                // Update payment with transaction ID and status
                // Do not attach course here; wait for NOTIFY_URL callback
                $payment->update([
                    'status' => $result['status'], // 'pending' or 'approved' from gateway
                    'transaction_id' => $result['transaction_id'],
                ]);

                Log::info('Payment status updated in completePayment', [
                    'payment_id' => $paymentId,
                    'status' => $result['status'],
                    'transaction_id' => $result['transaction_id'],
                ]);

                // Schedule status check for pending payments
                if ($result['status'] === 'pending') {
                    \Illuminate\Support\Facades\Queue::later(
                        now()->addMinutes(5),
                        new \App\Jobs\CheckUPCPaymentStatus($paymentId)
                    );
                    Log::info('Scheduled UPC payment status check', [
                        'payment_id' => $paymentId,
                    ]);
                }

                return $payment;
            } catch (Exception $e) {
                Log::error('Payment completion failed', [
                    'error' => $e->getMessage(),
                    'payment_id' => $paymentId,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new Exception("Payment completion failed: {$e->getMessage()}");
            }
        });
    }
}