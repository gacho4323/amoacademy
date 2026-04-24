<?php

namespace App\Services;

use App\Interfaces\UserInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    private UserInterface $userInterface;

    public function __construct(UserInterface $userInterface)
    {
        $this->userInterface = $userInterface;
    }

    public function getAllUsers($perPage = 10)
    {
        return $this->userInterface->getAllUsers($perPage);
    }

    public function getUserById(int $userId): User
    {
        return $this->userInterface->getUserById($userId);
    }

    public function createUser(array $data): User
    {
        return $this->userInterface->createUser($data);
    }

    public function updateUser(int $userId, array $data): User
    {
        return $this->userInterface->updateUser($userId, $data);
    }

    public function getUserCourses(int $userId): Collection
    {
        return $this->userInterface->getUserCourses($userId);
    }

    public function getAvailableCourses(): Collection
    {
        return $this->userInterface->getAvailableCourses();
    }

    public function assignCoursesToUser(int $userId, array $courseIds): void
    {
        $this->userInterface->assignCoursesToUser($userId, $courseIds);
    }
}