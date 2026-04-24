<?php

namespace App\Repositories;

use App\Interfaces\UserInterface;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserInterface
{
    public function getAllUsers(int $perPage = 10): LengthAwarePaginator
    {
        return User::query()->paginate($perPage);
    }

    public function getUserById(int $userId): User
    {
        return User::findOrFail($userId);
    }

    public function createUser(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        return User::create($data);
    }

    public function updateUser(int $userId, array $data): User
    {
        $user = $this->getUserById($userId);
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if (isset($data['courses']) && is_array($data['courses'])) {
            Log::info('Assigning courses to user ' . $userId . ': ' . json_encode($data['courses']));
            $this->assignCoursesToUser($userId, $data['courses']);
        } else {
            Log::info('No courses provided for user ' . $userId);
        }
        $user->update($data);

        return $user;
    }

    public function getUserCourses(int $userId): Collection
    {
        return User::findOrFail($userId)->courses()->get();
    }

    public function getAvailableCourses(): Collection
    {
        return Course::all();
    }

    public function assignCoursesToUser(int $userId, array $courseIds): void
    {
        $user = $this->getUserById($userId);
        Log::info('Syncing courses for user ' . $userId . ': ' . json_encode($courseIds));
        $user->courses()->sync($courseIds);
    }
}