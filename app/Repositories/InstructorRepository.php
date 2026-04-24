<?php

namespace App\Repositories;

use App\Interfaces\InstructorInterface;
use App\Models\Author;
use Illuminate\Database\Eloquent\Collection;

class InstructorRepository implements InstructorInterface
{
    public function getAllInstructors(): Collection
    {
        return Author::with(['courses', 'courses.category'])->get();
    }

    public function getInstructorWithDetails(int $authorId): Author
    {
        return Author::with([
            'courses' => function ($query) {
                $query->with(['videos', 'ebooks'])
                    ->select('id', 'title', 'author_id', 'type');
            },
            'ebooks' => function ($query) {
                $query->select('id', 'title', 'author_id');
            },
        ])->findOrFail($authorId);
    }

    public function createInstructor(array $data): Author
    {
        return Author::create($data);
    }

    public function updateInstructor(int $authorId, array $data): Author
    {
        $author = $this->getInstructorWithDetails($authorId);
        $author->update($data);
        
        return $author;
    }
}