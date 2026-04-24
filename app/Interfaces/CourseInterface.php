<?php

namespace App\Interfaces;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CourseInterface
{
    public function getAllCourses(): LengthAwarePaginator;
    public function getPopularCourses(int $limit = 10): Collection;
    public function getFeaturedCourses(int $limit = 10): Collection;
    public function getNewCourses(int $limit = 10): Collection;
    public function getRecommendedCourses(int $userId, int $limit = 10): Collection;
    public function createCourse(array $data): Course;
    public function updateCourse(Course $course, array $data): Course;
    public function deleteCourse(Course $course): void;
    public function extendCourse(Course $course, User $user, string $extensionType): array;
    public function getCoursesByCategory(string $categorySlug): ?array;


    /**
     * Fetch a course for editing with related data.
     *
     * @param int $courseId
     * @return Course
     */
    public function getCourseForEdit(int $courseId): Course;

    /**
     * Update a course with optional file uploads.
     *
     * @param Course $course
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return Course
     */
    public function updateCourseWithFiles(Course $course, array $data, array $files): Course;

    /**
     * Fetch all courses for a given user with related data.
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserCourses(int $userId): Collection;

        /**
     * Create a course with optional file uploads.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return Course
     */
    public function createCourseWithFiles(array $data, array $files): Course;
}