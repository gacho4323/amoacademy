<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    private OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(): JsonResponse
    {
        try {
            $orders = $this->orderService->getAllOrders();
            return response()->json([
                'success' => true,
                'data' => OrderResource::collection($orders),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch orders: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch orders'], 500);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id);
            return response()->json([
                'success' => true,
                'data' => new OrderResource($order)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch order for editing: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch order details'], 500);
        }
    }
}