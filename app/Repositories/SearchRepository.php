<?php

namespace App\Repositories;

use App\Interfaces\SearchInterface;
use App\Models\Author;
use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;

class SearchRepository implements SearchInterface
{
    /**
     * Search authors by name.
     */
    public function searchAuthors(string $query): Collection
    {
        $authors = Author::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
            ->select('id', 'name')
            ->limit(5)
            ->get();

        return $authors;
    }

    /**
     * Search courses by title.
     */
    public function searchCourses(string $query): Collection
    {
        $courses = Course::whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($query) . '%'])
            ->with('author:id,name')
            ->select('id', 'title', 'author_id')
            ->limit(5)
            ->get();

        return $courses;
    }
}