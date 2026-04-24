<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'amount',
        'currency',
        'converted_amount',
        'converted_currency',
        'payment_gateway',
        'payment_id',
        'transaction_id',
        'token',
        'status',
        'approval_code',
        'rrn',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}