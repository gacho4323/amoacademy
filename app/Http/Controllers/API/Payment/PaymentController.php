<?php

namespace App\Http\Controllers\API\Payment;

use App\Http\Controllers\Controller;
use App\Jobs\SendDesignCourseMaterial;
use App\Mail\OrderConfirmation;
use App\Models\Course;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\Payments\UPCPaymentGateway;
use App\Services\Payments\UplatnicaPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use App\Jobs\CreateMinimaxInvoice;

class PaymentController extends Controller
{
    private PaymentService $paymentService;
    private const DEFAULT_FRONTEND_URL = 'https://amoacademy.net';

    public function __construct()
    {
        // PaymentService will be instantiated dynamically
    }

    public function initiate(Request $request, Course $course): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'gateway' => ['required', 'string', 'in:paypal,upc,uplatnica'],
        ]);

        Log::debug('Payment initiation request', [
            'gateway' => $request->gateway,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'request_ip' => $request->ip(),
        ]);

        try {
            $this->paymentService = new PaymentService($request->gateway);
            $paymentData = $this->paymentService->processPayment($course, $user, $request->gateway);

            Log::info('Payment initiated', [
                'payment_id' => $paymentData['payment_id'] ?? null,
                'redirect_url' => $paymentData['redirect_url'] ?? null,
                'form_data' => $paymentData['form_data'] ?? null,
            ]);

            return response()->json([
                'message' => 'Payment initiated successfully',
                'redirect_url' => $paymentData['redirect_url'],
                'form_data' => $paymentData['form_data'],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'error' => $e->getMessage(),
                'gateway' => $request->gateway,
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function execute(Request $request): Response
    {
        $paymentId = $this->extractPaymentIdFromExecuteRequest($request);
        $transactionId = $this->extractTransactionIdFromExecuteRequest($request);

        Log::info('Executing payment', [
            'paymentId' => $paymentId,
            'transactionId' => $transactionId,
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        if (!$paymentId) {
            Log::warning('Missing paymentId in execute', [
                'request' => $request->all(),
                'query' => $request->query(),
                'body' => $request->all(),
            ]);
            return redirect()->to($this->buildCheckoutErrorUrl('Missing payment ID'));
        }

        try {
            $payment = Payment::where('payment_id', $paymentId)->first();
            if (!$payment) {
                Log::warning('Payment not found', ['paymentId' => $paymentId]);
                return redirect()->to($this->buildCheckoutErrorUrl('Payment not found'));
            }

            Auth::login($payment->user);
            $this->paymentService = new PaymentService($payment->payment_gateway);
            $payment = $this->paymentService->completePayment($paymentId, $transactionId);

            Log::info('Payment executed', [
                'payment_id' => $payment->payment_id,
                'status' => $payment->status,
            ]);

            // For PayPal, attach course immediately if status is completed
            if ($payment->payment_gateway === 'paypal' && $payment->status === 'approved') {
                $this->fulfillSuccessfulPayment($payment, 'execute');
            }

            // Redirect to pending page for UPC payments with preauthorization
            if ($payment->payment_gateway === 'upc' && $payment->status === 'pending') {
                return redirect()->away(
                    env('FRONTEND_URL', 'https://amoacademy.net') . '/checkout/pending?payment_id=' . urlencode($payment->payment_id)
                );
            }

            $redirectUrl = $this->buildCheckoutRedirectUrl($payment->status, $payment->payment_id, false);
            Log::info('Redirecting to frontend', [
                'payment_id' => $payment->payment_id,
                'status' => $payment->status,
                'redirect_url' => $redirectUrl,
            ]);
            return redirect()->away($redirectUrl);
        } catch (\Exception $e) {
            Log::error('Payment execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->away($this->buildCheckoutErrorUrl($e->getMessage()));
        }
    }

    public function cancel(Request $request): Response
    {
        $paymentId = $request->query('OrderID') ?? $request->input('OrderID');

        Log::info('Cancel payment request', [
            'paymentId' => $paymentId,
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        if (!$paymentId) {
            Log::warning('Missing payment ID in cancel', [
                'request' => $request->all(),
                'query' => $request->query(),
                'body' => $request->all(),
            ]);
            return redirect()->to(
                $this->buildCheckoutErrorUrl('Missing payment ID')
            );
        }

        $payment = Payment::where('payment_id', $paymentId)->first();
        if ($payment) {
            $payment->update(['status' => 'failed']);
            Log::info('Payment cancelled', ['payment_id' => $paymentId]);
        } else {
            Log::warning('Payment not found in cancel', ['paymentId' => $paymentId]);
        }

        return redirect()->to(
            $this->buildCheckoutErrorUrl('Payment cancelled')
        );
    }

    public function notify(Request $request): Response
    {
        $rawBody = file_get_contents('php://input');
        Log::info('UPC Notify raw request', [
            'raw_body' => $rawBody,
            'raw_body_hex' => bin2hex($rawBody),
            'raw_body_length' => strlen($rawBody),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
        ]);

        // Clean the input
        $rawBody = trim($rawBody);
        $rawBody = preg_replace('/^[\xEF\xBB\xBF]/', '', $rawBody); // Remove UTF-8 BOM
        $rawBody = str_replace(["\r\n", "\r", "\n"], '', $rawBody); // Remove line endings
        $rawBody = preg_replace('/[\x00-\x1F\x7F]/', '', $rawBody); // Remove control characters

        // Parse form data
        parse_str($rawBody, $requestData);
        if (!is_array($requestData) || empty($requestData)) {
            Log::warning('Failed to parse UPC notify data', [
                'raw_body' => $rawBody,
                'raw_body_hex' => bin2hex($rawBody),
                'content_type' => $request->header('Content-Type'),
            ]);
            return $this->buildNotifyResponse([], 'reverse', 'Invalid data format');
        }

        Log::info('UPC Notify parsed data', ['request' => $requestData]);

        $paymentId = $requestData['OrderID'] ?? null;
        $tranCode = $requestData['TranCode'] ?? '';
        $transactionId = $requestData['XID'] ?? '';
        $receivedSignature = $requestData['Signature'] ?? null;
        $approvalCode = $requestData['ApprovalCode'] ?? '';
        $rrn = $requestData['Rrn'] ?? '';
        $customer_email = $requestData['Email'] ?? null;

        if (!$paymentId || !$receivedSignature) {
            Log::warning('Invalid UPC notify data', [
                'paymentId' => $paymentId,
                'receivedSignature' => $receivedSignature,
                'request' => $requestData,
            ]);
            return $this->buildNotifyResponse($requestData, 'reverse', 'Missing OrderID or Signature');
        }

        $payment = Payment::where('payment_id', $paymentId)->first();
        if (!$payment) {
            Log::warning('Payment not found in notify', ['paymentId' => $paymentId]);
            return $this->buildNotifyResponse($requestData, 'reverse', 'Payment not found');
        }

        // Verify signature
        if (!UPCPaymentGateway::verifySignature($requestData, $receivedSignature)) {
            Log::error('UPC signature verification failed', [
                'paymentId' => $paymentId,
                'received_signature' => $receivedSignature,
                'request' => $requestData,
            ]);
            return $this->buildNotifyResponse($requestData, 'reverse', 'Invalid signature');
        }

        // Determine status based on TranCode
        $status = $tranCode === '000' ? 'completed' : 'failed';

        // Update payment with additional fields
        $payment->update([
            'status' => $status,
            'transaction_id' => $transactionId,
            'approval_code' => $approvalCode,
            'rrn' => $rrn,
        ]);

        // Attach course to user and send email if completed
        if ($status === 'completed') {
            $emailToUse = $customer_email ?: $payment->user->email;
            $this->fulfillSuccessfulPayment($payment, 'NOTIFY_URL', $emailToUse);
        }

        Log::info('Payment notify processed', [
            'payment_id' => $payment->payment_id,
            'status' => $payment->status,
            'transaction_id' => $transactionId,
            'tran_code' => $tranCode,
        ]);

        return $this->buildNotifyResponse(
            $requestData,
            $status === 'completed' ? 'approve' : 'reverse',
            $status === 'completed' ? 'ok' : 'Transaction not approved'
        );
    }

    private function buildNotifyResponse(array $data, string $action, string $reason): Response
    {
        $responseLines = [
            "MerchantID=" . ($data['MerchantID'] ?? ''),
            "TerminalID=" . ($data['TerminalID'] ?? ''),
            "OrderID=" . ($data['OrderID'] ?? ''),
            "Delay=" . ($data['Delay'] ?? ''),
            "Currency=" . ($data['Currency'] ?? ''),
            "TotalAmount=" . ($data['TotalAmount'] ?? ''),
            "XID=" . ($data['XID'] ?? ''),
            "PurchaseTime=" . ($data['PurchaseTime'] ?? ''),
            "Response.action=" . $action,
            "Response.reason=" . $reason,
            "Response.forwardUrl=",
        ];

        $responseBody = implode("\n", $responseLines);
        Log::info('UPC Notify response', ['response' => $responseBody]);

        return response($responseBody, 200, ['Content-Type' => 'text/plain']);
    }

    public function status(string $paymentId): JsonResponse
    {
        $payment = Payment::where('payment_id', $paymentId)->first();
        if (!$payment) {
            return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        // If payment is already completed, return immediately
        if ($payment->status === 'completed') {
            Log::info('Payment already completed, skipping status check', [
                'payment_id' => $paymentId,
                'status' => $payment->status,
            ]);
            return response()->json([
                'status' => $payment->status,
                'redirect_url' => $this->buildCheckoutRedirectUrl('completed', $payment->payment_id, true),
            ], 200);
        }

        if ($payment->payment_gateway === 'upc' && $payment->status === 'pending') {
            $statusData = UPCPaymentGateway::queryTransactionStatus($paymentId);

            // Only update if the status is different to avoid unnecessary writes
            if ($statusData['status'] !== $payment->status) {
                $payment->update([
                    'status' => $statusData['status'] === 'approved' ? 'completed' : $statusData['status'],
                    'transaction_id' => $statusData['transaction_id'] ?: $payment->transaction_id,
                    'approval_code' => $statusData['approval_code'] ?? $payment->approval_code,
                    'rrn' => $statusData['rrn'] ?? $payment->rrn,
                ]);

                if ($payment->status === 'completed') {
                    $this->fulfillSuccessfulPayment($payment, 'status check');
                }
            }
        }

        return response()->json([
            'status' => $payment->status,
            'redirect_url' => $payment->status === 'completed'
                ? $this->buildCheckoutRedirectUrl('completed', $payment->payment_id, true)
                : $this->buildCheckoutRedirectUrl('pending', $payment->payment_id, true),
        ], 200);
    }

    public function initiateUplatnica(Request $request, Course $course): JsonResponse
    {
        $user = Auth::user();
        Log::debug('Initiate Uplatnica Request', [
            'user' => $user ? $user->toArray() : null,
            'token' => $request->bearerToken(),
            'course_id' => $course->id,
            'request_ip' => $request->ip(),
            'headers' => $request->headers->all(),
        ]);

        if (!$user) {
            Log::warning('No authenticated user found for uplatnica payment', [
                'token' => $request->bearerToken(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $this->paymentService = new PaymentService('uplatnica');
            $paymentData = $this->paymentService->processPayment($course, $user, 'uplatnica');

            $uplatnicaGateway = new UplatnicaPaymentGateway();
            $uplatnicaGateway->sendUplatnicaInstructions($paymentData['payment']);

            Log::info('Uplatnica payment initiated successfully', [
                'payment_id' => $paymentData['payment']->payment_id,
                'user_id' => $user->id,
            ]);
            CreateMinimaxInvoice::dispatch($paymentData['payment']->payment_id);
            return response()->json([
                'message' => 'Uplatnica payment initiated successfully. Check your email for payment instructions.',
                'payment_id' => $paymentData['payment']->payment_id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Uplatnica payment initiation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? 'N/A',
                'course_id' => $course->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function extractPaymentIdFromExecuteRequest(Request $request): ?string
    {
        return $request->query('OrderID')
            ?? $request->input('OrderID')
            ?? $request->query('paymentId')
            ?? $request->input('paymentId')
            ?? $request->query('token');
    }

    private function extractTransactionIdFromExecuteRequest(Request $request): string
    {
        return $request->query('PayerID')
            ?? $request->input('PayerID')
            ?? $request->input('XID')
            ?? '';
    }

    private function buildCheckoutErrorUrl(string $message): string
    {
        return $this->frontendUrl() . '/checkout/error?message=' . urlencode($message);
    }

    private function buildCheckoutRedirectUrl(string $status, string $paymentId, bool $pendingInsteadOfError): string
    {
        if ($status === 'pending') {
            $path = 'pending';
        } elseif ($status === 'completed') {
            $path = 'success';
        } else {
            $path = $pendingInsteadOfError ? 'pending' : 'error';
        }

        return $this->frontendUrl() . '/checkout/' . $path . '?payment_id=' . urlencode($paymentId);
    }

    private function frontendUrl(): string
    {
        return env('FRONTEND_URL', self::DEFAULT_FRONTEND_URL);
    }

    private function fulfillSuccessfulPayment(Payment $payment, string $context, ?string $emailToUse = null): void
    {
        $payment->user->courses()->syncWithoutDetaching([$payment->course_id]);
        Log::info('Course attached to user via ' . $context, [
            'payment_id' => $payment->payment_id,
            'user_id' => $payment->user_id,
            'course_id' => $payment->course_id,
        ]);

        CreateMinimaxInvoice::dispatch($payment->payment_id);
        SendDesignCourseMaterial::dispatch($payment->user);

        $this->sendOrderConfirmationEmail($payment, $emailToUse, $context);
    }

    private function sendOrderConfirmationEmail(Payment $payment, ?string $emailToUse = null, string $context = 'payment flow'): void
    {
        $email = $emailToUse ?: $payment->user->email;
        if (!$email) {
            Log::warning('No valid email address found for order confirmation', [
                'payment_id' => $payment->payment_id,
                'context' => $context,
                'user_email' => $payment->user->email,
            ]);
            return;
        }

        try {
            Mail::to($email)->send(
                new OrderConfirmation(
                    $payment->payment_id,
                    $payment->amount,
                    $payment->payment_gateway,
                    $email
                )
            );
            Log::info('Order confirmation email sent', [
                'payment_id' => $payment->payment_id,
                'email' => $email,
                'context' => $context,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'payment_id' => $payment->payment_id,
                'email' => $email,
                'context' => $context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
