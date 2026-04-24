<?php

namespace App\Services;

use App\Interfaces\OrderInterface;
use App\Models\CourseUser;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderService
{
    private OrderInterface $orderInterface;

    public function __construct(OrderInterface $orderInterface)
    {
        $this->orderInterface = $orderInterface;
    }

    public function getAllOrders(): LengthAwarePaginator
    {
        return $this->orderInterface->getAllOrders();
    }

    public function getOrderById(int $orderId): CourseUser
    {
        return $this->orderInterface->getOrderById($orderId);
    }
}