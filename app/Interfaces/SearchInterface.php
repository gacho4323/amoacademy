<?php

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Collection;

interface SearchInterface
{
    public function searchAuthors(string $query): Collection;
    public function searchCourses(string $query): Collection;
}