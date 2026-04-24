<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Author extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'bio', 'trailer_video'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function ebooks(): HasMany
    {
        return $this->hasMany(Ebook::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }
}
