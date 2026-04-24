<?php

namespace App\Services\Payments;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Course;
use App\Models\User;
use App\Models\Payment; // Add this import
use Illuminate\Support\Facades\Mail;
use App\Mail\UplatnicaInstructions;

class UplatnicaPaymentGateway implements PaymentGatewayInterface
{
    public function createPayment(Course $course, User $user, float $amount): array
    {
        $paymentId = 'UPL-' . uniqid() . '-' . time();

        return [
            'payment_id' => $paymentId,
            'approval_url' => null, // No redirect for bank transfer
            'form_data' => null,
        ];
    }

    public function executePayment(string $paymentId, string $transactionId): array
    {
        // Bank transfer payments are completed manually after verification
        return [
            'status' => 'pending',
            'transaction_id' => null,
        ];
    }

    public function refundPayment(string $paymentId, float $amount): array
    {
        // Implement refund logic if needed
        throw new \Exception('Refunds for bank transfer not supported');
    }

    public function sendUplatnicaInstructions(Payment $payment)
    {
        try {
            Mail::to($payment->user->email)->send(new UplatnicaInstructions($payment));
        } catch (\Exception $e) {
            \Log::error('Failed to send uplatnica instructions email', [
                'payment_id' => $payment->payment_id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to send payment instructions');
        }
    }
}