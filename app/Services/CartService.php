<?php

namespace App\Services;

use App\Interfaces\CartInterface;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;

class CartService
{
    private CartInterface $cartRepository;

    public function __construct(CartInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    public function getUserCart(int $userId): Cart
    {
        return $this->cartRepository->getOrCreateCart($userId);
    }

    public function addCourseToCart(int $userId, int $courseId): Cart
    {
        return DB::transaction(function () use ($userId, $courseId) {
            $cart = $this->cartRepository->getOrCreateCart($userId);
            $this->cartRepository->addCourse($cart, $courseId);

            return $cart->load('courses');
        });
    }

    public function removeCourseFromCart(int $userId, int $courseId): Cart
    {
        return DB::transaction(function () use ($userId, $courseId) {
            $cart = $this->cartRepository->getOrCreateCart($userId);
            $this->cartRepository->removeCourse($cart, $courseId);

            return $cart->load('courses');
        });
    }

    public function clearCart(int $userId): Cart
    {
        return DB::transaction(function () use ($userId) {
            $cart = $this->cartRepository->getOrCreateCart($userId);
            $this->cartRepository->clearCart($cart);
            
            return $cart->load('courses');
        });
    }
}