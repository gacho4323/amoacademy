<?php

namespace App\Repositories;

use App\Interfaces\OrderInterface;
use App\Models\CourseUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class OrderRepository implements OrderInterface
{
    public function getAllOrders(): LengthAwarePaginator
    {
        $query = CourseUser::with(['user', 'course'])
            ->leftJoin('payments', function ($join) {
                $join->on('course_user.user_id', '=', 'payments.user_id')
                     ->on('course_user.course_id', '=', 'payments.course_id');
            })
            ->select(
                'course_user.*',
                'payments.status as payment_status',
                'payments.amount as payment_amount',
                'payments.currency as payment_currency'
            )
            ->orderBy('course_user.purchased_at', 'desc');

        // Log the raw SQL query for debugging
        Log::debug('OrderRepository getAllOrders SQL:', [
            'query' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $results = $query->paginate(10);
        //dd($results);

        // Log the results to verify data
        Log::debug('OrderRepository getAllOrders Results:', [
            'total' => $results->total(),
            'data' => $results->items(),
        ]);

        return $results;
    }

    public function getOrderById(int $orderId): CourseUser
    {
        $query = CourseUser::with(['user', 'course'])
            ->leftJoin('payments', function ($join) {
                $join->on('course_user.user_id', '=', 'payments.user_id')
                     ->on('course_user.course_id', '=', 'payments.course_id')
                     ->where('payments.status', '!=', 'pending');
            })
            ->select(
                'course_user.*',
                'payments.status as payment_status',
                'payments.amount as payment_amount',
                'payments.currency as payment_currency'
            )
            ->where('course_user.id', $orderId);

        // Log the query for debugging
        Log::debug('OrderRepository getOrderById SQL:', [
            'query' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $result = $query->firstOrFail();

        // Log the result
        Log::debug('OrderRepository getOrderById Result:', [
            'order_id' => $orderId,
            'data' => $result->toArray(),
        ]);

        return $result;
    }
}