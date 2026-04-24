<?php

namespace App\Services;

use App\Interfaces\InstructorInterface;
use App\Models\Author;
use Illuminate\Database\Eloquent\Collection;

class InstructorService
{
    private InstructorInterface $instructorInterface;

    public function __construct(InstructorInterface $instructorInterface)
    {
        $this->instructorInterface = $instructorInterface;
    }

    public function getAllInstructors(): Collection
    {
        return $this->instructorInterface->getAllInstructors();
    }

    public function getInstructorWithDetails(int $authorId): Author
    {
        return $this->instructorInterface->getInstructorWithDetails($authorId);
    }

    public function createInstructor(array $data): Author
    {
        return $this->instructorInterface->createInstructor($data);
    }

    public function updateInstructor(int $authorId, array $data): Author
    {
        return $this->instructorInterface->updateInstructor($authorId, $data);
    }
}