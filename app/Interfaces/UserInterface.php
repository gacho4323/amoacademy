<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserInterface
{
    public function getAllUsers(int $perPage = 10): LengthAwarePaginator;

    public function getUserById(int $userId): User;

    public function createUser(array $data): User;

    public function updateUser(int $userId, array $data): User;

    public function getUserCourses(int $userId): Collection;

    public function getAvailableCourses(): Collection;

    public function assignCoursesToUser(int $userId, array $courseIds): void;
}