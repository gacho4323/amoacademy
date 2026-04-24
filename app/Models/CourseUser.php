<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseUser extends Model
{
    protected $table = 'course_user';

    protected $fillable = [
        'course_id',
        'user_id',
        'purchased_at',
        'course_expiry_date',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'course_expiry_date' => 'date',
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