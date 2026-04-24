<?php

namespace App\Repositories;

use App\Interfaces\CartInterface;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;

class CartRepository implements CartInterface
{
    public function getOrCreateCart(int $userId): Cart
    {
        try {
            return Cart::firstOrCreate(
                ['user_id' => $userId],
                ['user_id' => $userId]
            )->load('courses');
        } catch (\Exception $e) {
            throw new \Exception("Failed to get or create cart: " . $e->getMessage());
        }
    }

    public function addCourse(Cart $cart, int $courseId): void
    {
        try {
            if (!$cart->courses()->where('course_id', $courseId)->exists()) {
                $cart->courses()->attach($courseId);
            }
        } catch (\Exception $e) {
            throw new \Exception("Failed to add course to cart: " . $e->getMessage());
        }
    }

    public function removeCourse(Cart $cart, int $courseId): void
    {
        try {
            $cart->courses()->detach($courseId);
        } catch (\Exception $e) {
            throw new \Exception("Failed to remove course from cart: " . $e->getMessage());
        }
    }

    public function clearCart(Cart $cart): void
    {
        try {
            $cart->courses()->detach();
        } catch (\Exception $e) {
            throw new \Exception("Failed to clear cart: " . $e->getMessage());
        }
    }
}