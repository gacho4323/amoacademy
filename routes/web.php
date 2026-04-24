<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\MinimaxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])
    ->where('provider', 'google|facebook')
    ->name('social.callback.web');

Route::get('/auth/success', function (Request $request) {
    $token = $request->query('token');
    $type = $request->query('type');

    if (!$token) {
        \Log::warning('Missing token in /auth/login', ['request' => $request->all()]);
        return redirect()->to(env('FRONTEND_URL', 'https://amoacademy.net') . '/auth/error?message=' . urlencode('Missing token'));
    }

    // Instead of redirecting to the same URL, redirect to a frontend route that handles token processing
    $frontendSuccessUrl = env('FRONTEND_URL', 'https://amoacademy.net') . '/login-success?token=' . urlencode($token) . '&type=' . urlencode($type);
    \Log::info('Redirecting to frontend success page', ['url' => $frontendSuccessUrl]);
    return redirect()->to($frontendSuccessUrl);
})->name('web.auth.success');

Route::get('/auth/error', function (Request $request) {
    $message = $request->query('message') ?: 'Authentication failed';
    \Illuminate\Support\Facades\Log::warning('Authentication error', ['message' => $message]);
    return redirect()->to(env('FRONTEND_URL', 'https://amoacademy.net') . '/auth/error?message=' . urlencode($message));
})->name('auth.error');

// Existing payment-related routes (unchanged)
Route::get('/success', function (Request $request) {
    $paymentId = $request->query('OrderID');
    if (!$paymentId) {
        \Illuminate\Support\Facades\Log::warning('Missing payment ID in /success', ['request' => $request->all()]);
        return redirect()->to(env('FRONTEND_URL', 'https://amoacademy.net') . '/checkout/error?message=' . urlencode('Missing payment ID'));
    }
    return redirect()->to(env('FRONTEND_URL', 'https://amoacademy.net') . '/checkout/success?payment_id=' . urlencode($paymentId));
})->name('web.payment.success');

Route::get('/payments/success', function (Request $request) {
    $paymentId = $request->query('payment_id');
    \Illuminate\Support\Facades\Log::info('Redirect to /payments/success', ['payment_id' => $paymentId]);
    return redirect()->to(
        env('FRONTEND_URL', 'https://amoacademy.net') . '/checkout/success?payment_id=' . urlencode($paymentId)
    );
})->name('payment.success');

Route::match(['get', 'post'], '/failure', function (Request $request) {
    \Illuminate\Support\Facades\Log::warning('Received request to /failure', [
        'method' => $request->method(),
        'query' => $request->query(),
        'headers' => $request->headers->all(),
        'body' => $request->all(),
    ]);
    $message = $request->query('message') ?: 'Payment failed';
    return redirect()->to(env('FRONTEND_URL', 'https://amoacademy.net') . '/checkout/error?message=' . urlencode($message));
})->name('web.payment.failure');

Route::get('/pending', function (Request $request) {
    $paymentId = $request->query('payment_id');
    \Illuminate\Support\Facades\Log::info('Redirect to /pending', ['payment_id' => $paymentId]);
    return redirect()->to(
        env('FRONTEND_URL', 'https://amoacademy.net') . '/checkout/pending?payment_id=' . urlencode($paymentId)
    );
})->name('web.payment.pending');

Route::get('/ebooks/design/{filename}', function ($filename) {
    $path = storage_path("app/ebooks/design/" . $filename);

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->download($path);
})->where('filename', '.*');
