<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'category_id',
        'title',
        'description',
        'type',
        'price',
        'original_price',
        'is_featured',
        'language',
        'audio_language',
        'item_code'
    ];

    protected $casts = [
        'is_featured'    => 'boolean',
        'language'       => 'array', // Store as JSON array: ['sr', 'en']
        'audio_language' => 'array', // Store as JSON array: ['sr', 'en']
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class)->orderBy('order');
    }

    public function ebooks(): HasMany
    {
        return $this->hasMany(Ebook::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('course_expiry_date')
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
