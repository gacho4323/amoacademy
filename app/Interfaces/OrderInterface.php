<?php

namespace App\Interfaces;

use App\Models\CourseUser;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderInterface
{
    public function getAllOrders(): LengthAwarePaginator;

    public function getOrderById(int $orderId): CourseUser;
}