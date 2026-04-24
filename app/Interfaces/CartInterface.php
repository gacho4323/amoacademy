<?php

namespace App\Interfaces;

use App\Models\Cart;

interface CartInterface
{
    public function getOrCreateCart(int $userId): Cart;
    public function addCourse(Cart $cart, int $courseId): void;
    public function removeCourse(Cart $cart, int $courseId): void;
    public function clearCart(Cart $cart): void;
}