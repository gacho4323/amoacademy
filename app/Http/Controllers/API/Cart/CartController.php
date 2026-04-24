<?php

namespace App\Http\Controllers\API\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCourseToCartRequest;
use App\Http\Requests\RemoveCourseFromCartRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function show(): JsonResponse
    {
        $cart = $this->cartService->getUserCart(auth()->id());

        return response()->json(new CartResource($cart));
    }

    public function addCourse(AddCourseToCartRequest $request): JsonResponse
    {
        $cart = $this->cartService->addCourseToCart(
            auth()->id(),
            $request->validated()['course_id']
        );

        return response()->json(new CartResource($cart));
    }

    public function removeCourse(RemoveCourseFromCartRequest $request): JsonResponse
    {
        $cart = $this->cartService->removeCourseFromCart(
            auth()->id(),
            $request->validated()['course_id']
        );

        return response()->json(new CartResource($cart));
    }

    public function clear(): JsonResponse
    {
        $cart = $this->cartService->clearCart(auth()->id());
        
        return response()->json(new CartResource($cart));
    }
}