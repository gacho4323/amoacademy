<?php

namespace App\Interfaces;

use App\Models\Author;
use Illuminate\Database\Eloquent\Collection;

interface InstructorInterface
{
    public function getAllInstructors(): Collection;

    public function getInstructorWithDetails(int $authorId): Author;

    public function createInstructor(array $data): Author;

    public function updateInstructor(int $authorId, array $data): Author;
}