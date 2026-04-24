<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\UPCPaymentGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckUPCPaymentStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $paymentId;

    public function __construct(string $paymentId)
    {
        $this->paymentId = $paymentId;
    }

    public function handle()
    {
        Log::info('Checking UPC payment status', ['payment_id' => $this->paymentId]);

        try {
            $payment = Payment::where('payment_id', $this->paymentId)->first();
            if (!$payment) {
                Log::warning('Payment not found in CheckUPCPaymentStatus', ['payment_id' => $this->paymentId]);
                return;
            }

            if ($payment->status !== 'pending') {
                Log::info('Payment no longer pending, skipping status check', [
                    'payment_id' => $this->paymentId,
                    'status' => $payment->status,
                ]);
                return;
            }

            $statusData = UPCPaymentGateway::queryTransactionStatus($this->paymentId);

            $payment->update([
                'status' => $statusData['status'] === 'approved' ? 'completed' : $statusData['status'],
                'transaction_id' => $statusData['transaction_id'],
                'approval_code' => $statusData['approval_code'] ?? $payment->approval_code,
                'rrn' => $statusData['rrn'] ?? $payment->rrn,
            ]);

            if ($payment->status === 'completed') {
                $payment->user->courses()->syncWithoutDetaching([$payment->course_id]);
                Log::info('Course attached to user via CheckUPCPaymentStatus', [
                    'payment_id' => $this->paymentId,
                    'user_id' => $payment->user_id,
                    'course_id' => $payment->course_id,
                ]);
            }

            Log::info('UPC payment status updated', [
                'payment_id' => $this->paymentId,
                'status' => $payment->status,
                'transaction_id' => $statusData['transaction_id'],
            ]);

            // Reschedule job if still pending (up to a maximum number of attempts)
            if ($payment->status === 'pending') {
                $attempts = $this->job->attempts();
                if ($attempts < 10) {
                    Log::info('Rescheduling UPC payment status check', [
                        'payment_id' => $this->paymentId,
                        'attempt' => $attempts + 1,
                    ]);
                    \Illuminate\Support\Facades\Queue::later(
                        now()->addMinutes(5),
                        new self($this->paymentId)
                    );
                } else {
                    Log::warning('Max attempts reached for UPC payment status check', [
                        'payment_id' => $this->paymentId,
                        'attempts' => $attempts,
                    ]);
                    $payment->update(['status' => 'failed']);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to check UPC payment status', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Reschedule job on failure (up to a maximum number of attempts)
            $attempts = $this->job->attempts();
            if ($attempts < 10) {
                Log::info('Rescheduling UPC payment status check due to error', [
                    'payment_id' => $this->paymentId,
                    'attempt' => $attempts + 1,
                ]);
                \Illuminate\Support\Facades\Queue::later(
                    now()->addMinutes(5),
                    new self($this->paymentId)
                );
            } else {
                Log::warning('Max attempts reached for UPC payment status check', [
                    'payment_id' => $this->paymentId,
                    'attempts' => $attempts,
                ]);
                $payment = Payment::where('payment_id', $this->paymentId)->first();
                if ($payment) {
                    $payment->update(['status' => 'failed']);
                }
            }
        }
    }
}