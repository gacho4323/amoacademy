<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'path',
        'size',
        'mime_type',
        'order',
        'is_intro',
        'intro_duration',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}