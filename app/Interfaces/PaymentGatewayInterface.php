<?php

namespace App\Interfaces;

use App\Models\Course;
use App\Models\User;

interface PaymentGatewayInterface
{
    public function createPayment(Course $course, User $user, float $amount): array;

    public function executePayment(string $paymentId, string $transactionId): array;

    public function refundPayment(string $paymentId, float $amount): array;
}