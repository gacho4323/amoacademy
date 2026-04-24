<?php

namespace App\Services\Payments;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UPCPaymentGateway implements PaymentGatewayInterface
{
    public function createPayment(Course $course, User $user, float $amount): array
    {
        $merchantId = config('upc.merchant_id');
        $terminalId = config('upc.terminal_id');
        $currencyId = config('upc.currency_id');
        $locale = config('upc.locale');
        $purchaseTime = date('ymdHis');
        $delay = 1; // Set Delay=1 for preauthorization

        // Format amount based on currency (RSD: no decimals, UAH: *100)
        $formattedAmount = $currencyId === '980' ? (int) ($amount * 100) : (int) ($amount * 100); // Multiply by 100 for RSD too

        // Generate unique OrderID
        $maxAttempts = 3;
        $attempt = 0;
        do {
            $attempt++;
            $orderId = 'ORD' . now()->format('His') . $user->id . $course->id . Str::random(4);
            Log::debug('Generated UPC payment_id', ['orderId' => $orderId, 'attempt' => $attempt]);

            if (!Payment::where('payment_id', $orderId)->exists()) {
                break;
            }

            Log::warning('Duplicate payment_id detected', ['orderId' => $orderId, 'attempt' => $attempt]);
            if ($attempt >= $maxAttempts) {
                throw new \Exception('Unable to generate unique payment_id after ' . $maxAttempts . ' attempts');
            }
            usleep(100000);
        } while (true);

        // Prepare signature data
        $data = "$merchantId;$terminalId;$purchaseTime;$orderId,$delay;$currencyId;$formattedAmount;;";

        Log::debug('UPC signature data string', [
            'data' => $data,
            'merchantId' => $merchantId,
            'terminalId' => $terminalId,
            'purchaseTime' => $purchaseTime,
            'orderId' => $orderId,
            'delay' => $delay,
            'currencyId' => $currencyId,
            'amount' => $amount,
            'formattedAmount' => $formattedAmount,
        ]);

        // Load private key
        $privateKeyPath = config('upc.paths.private_key');
        if (!file_exists($privateKeyPath)) {
            Log::error('UPC private key not found', ['path' => $privateKeyPath]);
            throw new \Exception('UPC private key not found at ' . $privateKeyPath);
        }

        $privateKey = file_get_contents($privateKeyPath);
        $pkeyid = openssl_get_privatekey($privateKey);
        if (!$pkeyid) {
            Log::error('Failed to load UPC private key', ['path' => $privateKeyPath]);
            throw new \Exception('Failed to load UPC private key');
        }

        if (!openssl_sign($data, $signature, $pkeyid, OPENSSL_ALGO_SHA512)) {
            Log::error('UPC signature generation failed', ['data' => $data]);
            openssl_free_key($pkeyid);
            throw new \Exception('Failed to generate UPC signature');
        }

        openssl_free_key($pkeyid);
        $b64sign = base64_encode($signature);

        $gatewayUrl = config('upc.test_mode') ? config('upc.gateway_urls.test') : config('upc.gateway_urls.production');

        Log::info('UPC payment initiated', [
            'payment_id' => $orderId,
            'course_id' => $course->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'formatted_amount' => $formattedAmount,
            'signature' => $b64sign,
            'purchase_time' => $purchaseTime,
            'gateway_url' => $gatewayUrl,
        ]);

        return [
            'payment_id' => $orderId,
            'approval_url' => $gatewayUrl,
            'form_data' => [
                'Version' => '1',
                'MerchantID' => $merchantId,
                'TerminalID' => $terminalId,
                'TotalAmount' => $formattedAmount,
                'Currency' => $currencyId,
                'locale' => $locale,
                'PurchaseTime' => $purchaseTime,
                'OrderID' => $orderId,
                'Signature' => $b64sign,
                'Delay' => $delay,
            ],
        ];
    }

    public function executePayment(string $paymentId, string $transactionId): array
    {
        $payment = Payment::where('payment_id', $paymentId)->firstOrFail();
        $tranCode = request()->input('TranCode', '');
        $delay = request()->input('Delay', '');

        Log::info('UPC executePayment called', [
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId,
            'tran_code' => $tranCode,
            'delay' => $delay,
            'current_status' => $payment->status,
            'request_data' => request()->all(),
        ]);

        // If NOTIFY_URL has already marked the payment as completed, respect that status
        if ($payment->status === 'completed') {
            Log::info('Payment already completed by NOTIFY_URL', [
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
            ]);
            return [
                'status' => 'completed',
                'transaction_id' => $payment->transaction_id ?? $transactionId,
                'tran_code' => $tranCode,
            ];
        }

        // For preauthorization (Delay=1), set status to pending
        $status = $delay === '1' ? 'pending' : ($tranCode === '000' ? 'completed' : 'failed');

        if ($tranCode !== '000' && $delay !== '1') {
            Log::warning('UPC transaction not approved in executePayment', [
                'payment_id' => $paymentId,
                'tran_code' => $tranCode,
            ]);
        }

        return [
            'status' => $status,
            'transaction_id' => request()->input('XID', $transactionId),
            'tran_code' => $tranCode,
        ];
    }
    public function refundPayment(string $paymentId, float $amount): array
    {
        Log::info('UPC refund attempted', [
            'payment_id' => $paymentId,
            'amount' => $amount,
        ]);

        $payment = Payment::where('payment_id', $paymentId)->firstOrFail();
        $merchantId = config('upc.merchant_id');
        $terminalId = config('upc.terminal_id');
        $currencyId = config('upc.currency_id');
        $purchaseTime = date('ymdHis');
        $formattedAmount = $currencyId === '980' ? (int) ($amount * 100) : (int) $amount;

        // Prepare refund request data
        $data = [
            'MerchantID' => $merchantId,
            'TerminalID' => $terminalId,
            'OrderID' => $paymentId,
            'Currency' => $currencyId,
            'TotalAmount' => $formattedAmount,
            'PurchaseTime' => $purchaseTime,
            'ApprovalCode' => $payment->approval_code ?? '',
            'Rrn' => $payment->rrn ?? '',
            'RefundAmount' => $formattedAmount,
        ];

        // Generate signature
        $signatureData = implode(';', [
            $merchantId,
            $terminalId,
            $purchaseTime,
            $paymentId,
            $currencyId,
            $formattedAmount,
            '',
            $payment->approval_code ?? '',
            $payment->rrn ?? '',
            $formattedAmount,
            '',
        ]);

        $privateKeyPath = config('upc.paths.private_key');
        $privateKey = file_get_contents($privateKeyPath);
        $pkeyid = openssl_get_privatekey($privateKey);

        if (!openssl_sign($signatureData, $signature, $pkeyid, OPENSSL_ALGO_SHA512)) {
            Log::error('UPC refund signature generation failed', ['data' => $signatureData]);
            openssl_free_key($pkeyid);
            throw new \Exception('Failed to generate UPC refund signature');
        }

        openssl_free_key($pkeyid);
        $data['Signature'] = base64_encode($signature);

        try {
            $response = \Illuminate\Support\Facades\Http::post('https://ecg.test.upc.ua/go/repayment', $data);
            $responseData = parse_str($response->body(), $parsed);

            Log::info('UPC refund response', ['response' => $responseData]);

            if (($responseData['TranCode'] ?? '') === '000') {
                $payment->update(['status' => 'refunded']);
                return ['status' => 'refunded', 'transaction_id' => $payment->transaction_id];
            }

            throw new \Exception('Refund failed: ' . ($responseData['ERROR'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('UPC refund request failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public static function verifySignature(array $data, string $receivedSignature): bool
    {
        // Load server certificate
        $certPath = config('upc.paths.certificate');
        if (!file_exists($certPath)) {
            Log::error('UPC server certificate not found', ['path' => $certPath]);
            return false;
        }

        $publicKey = file_get_contents($certPath);
        $pubkeyid = openssl_get_publickey($publicKey);
        if (!$pubkeyid) {
            Log::error('Failed to load UPC server certificate', [
                'path' => $certPath,
                'content' => substr($publicKey, 0, 100),
            ]);
            return false;
        }

        // Construct signature data string as per UPC documentation (Page 9)
        $fields = [
            'MerchantID' => $data['MerchantID'] ?? config('upc.merchant_id'),
            'TerminalID' => $data['TerminalID'] ?? config('upc.terminal_id'),
            'PurchaseTime' => $data['PurchaseTime'] ?? '',
            'OrderID' => $data['OrderID'] ?? '',
            'Delay' => $data['Delay'] ?? '',
            'XID' => $data['XID'] ?? '',
            'Currency' => $data['Currency'] ?? config('upc.currency_id'),
            'AltCurrency' => $data['AltCurrency'] ?? '',
            'TotalAmount' => isset($data['TotalAmount']) ? (string) floor((float) $data['TotalAmount']) : '',
            'AltTotalAmount' => $data['AltTotalAmount'] ?? '',
            'SD' => $data['SD'] ?? '',
            'TranCode' => $data['TranCode'] ?? '',
            'ApprovalCode' => $data['ApprovalCode'] ?? '',
        ];

        $signatureData = implode(';', [
            $fields['MerchantID'],
            $fields['TerminalID'],
            $fields['PurchaseTime'],
            $fields['OrderID'] . ($fields['Delay'] ? ',' . $fields['Delay'] : ''),
            $fields['XID'],
            $fields['Currency'] . ($fields['AltCurrency'] ? ',' . $fields['AltCurrency'] : ''),
            $fields['TotalAmount'] . ($fields['AltTotalAmount'] ? ',' . $fields['AltTotalAmount'] : ''),
            $fields['SD'],
            $fields['TranCode'],
            $fields['ApprovalCode'],
            '', // Single trailing semicolon
        ]);

        // Ensure no extra spaces or newlines
        $signatureData = str_replace(["\r", "\n", " "], '', $signatureData);

        Log::debug('UPC signature verification data', [
            'signature_data' => $signatureData,
            'received_signature' => $receivedSignature,
            'fields' => $fields,
            'public_key_snippet' => substr($publicKey, 0, 100),
        ]);

        // Decode the received signature
        $signature = base64_decode($receivedSignature, true);
        if ($signature === false) {
            Log::error('Failed to decode received signature', [
                'received_signature' => $receivedSignature,
                'length' => strlen($receivedSignature),
            ]);
            openssl_free_key($pubkeyid);
            return false;
        }

        $result = openssl_verify($signatureData, $signature, $pubkeyid, OPENSSL_ALGO_SHA512);
        if ($result === -1) {
            Log::error('OpenSSL SHA512 verification error', [
                'error' => openssl_error_string(),
                'signature_data' => $signatureData,
                'decoded_signature_length' => strlen($signature),
            ]);
        }

        openssl_free_key($pubkeyid);

        Log::debug('UPC signature verification result', [
            'result' => $result,
            'signature_data' => $signatureData,
            'decoded_signature_length' => strlen($signature),
            'algorithm' => 'SHA512',
        ]);

        return $result === 1;
    }


    public static function queryTransactionStatus(string $paymentId): array
    {
        $payment = Payment::where('payment_id', $paymentId)->firstOrFail();
        $merchantId = config('upc.merchant_id');
        $terminalId = config('upc.terminal_id');
        $currencyId = config('upc.currency_id');
        $purchaseTime = date('ymdHis', strtotime($payment->created_at));

        // Ensure TotalAmount is formatted correctly (no decimals for RSD)
        $totalAmount = (int) ($payment->converted_amount * 100); // Multiply by 100 for RSD

        $data = [
            'MerchantID' => $merchantId,
            'TerminalID' => $terminalId,
            'OrderID' => $paymentId,
            'Currency' => $currencyId,
            'TotalAmount' => $totalAmount,
            'PurchaseTime' => $purchaseTime,
        ];

        // Generate signature
        $signatureData = "$merchantId;$terminalId;$purchaseTime;$paymentId;$currencyId;$totalAmount;";

        Log::debug('UPC transaction status signature data', [
            'signature_data' => $signatureData,
            'data' => $data,
        ]);

        $privateKeyPath = config('upc.paths.private_key');
        if (!file_exists($privateKeyPath)) {
            Log::error('UPC private key not found', ['path' => $privateKeyPath]);
            throw new \Exception('UPC private key not found');
        }

        $privateKey = file_get_contents($privateKeyPath);
        $pkeyid = openssl_get_privatekey($privateKey);
        if (!$pkeyid) {
            Log::error('Failed to load UPC private key', ['path' => $privateKeyPath]);
            throw new \Exception('Failed to load UPC private key');
        }

        if (!openssl_sign($signatureData, $signature, $pkeyid, OPENSSL_ALGO_SHA512)) {
            Log::error('UPC signature generation failed for status query', ['data' => $signatureData]);
            openssl_free_key($pkeyid);
            throw new \Exception('Failed to generate UPC signature for status query');
        }

        openssl_free_key($pkeyid);
        $data['Signature'] = base64_encode($signature);

        Log::info('Querying UPC transaction status', ['payment_id' => $paymentId, 'data' => $data]);

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()->post('https://ecg.test.upc.ua/go/service/01', $data);
            $responseBody = $response->body();
            parse_str($responseBody, $responseData);

            Log::debug('UPC transaction status response', ['response' => $responseData]);

            if (isset($responseData['ERROR'])) {
                Log::error('UPC transaction status query failed', [
                    'payment_id' => $paymentId,
                    'error' => $responseData['ERROR'],
                    'response' => $responseData,
                ]);
                // Fallback to payment record status if already updated by NOTIFY_URL
                return [
                    'status' => $payment->status, // Use existing status to avoid marking as failed
                    'transaction_id' => $responseData['XID'] ?? $payment->transaction_id,
                    'tran_code' => $responseData['TranCode'] ?? '',
                    'approval_code' => $responseData['ApprovalCode'] ?? $payment->approval_code,
                    'rrn' => $responseData['Rrn'] ?? $payment->rrn,
                ];
            }

            return [
                'status' => ($responseData['TranCode'] ?? '') === '000' ? 'approved' : 'failed',
                'transaction_id' => $responseData['XID'] ?? $payment->transaction_id,
                'tran_code' => $responseData['TranCode'] ?? '',
                'approval_code' => $responseData['ApprovalCode'] ?? $payment->approval_code,
                'rrn' => $responseData['Rrn'] ?? $payment->rrn,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to query UPC transaction status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Fallback to payment record status
            return [
                'status' => $payment->status,
                'transaction_id' => $payment->transaction_id ?? '',
                'tran_code' => '',
                'approval_code' => $payment->approval_code ?? '',
                'rrn' => $payment->rrn ?? '',
            ];
        }
    }
}
