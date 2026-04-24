<?php

namespace App\Services;

use App\Interfaces\CourseInterface;
use App\Models\Course;
use App\Models\User;
use App\Notifications\CourseExtendedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseService
{
    private CourseInterface $courseRepository;

    public function __construct(CourseInterface $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    public function getAllCourses(): LengthAwarePaginator
    {
        return $this->courseRepository->getAllCourses();
    }

    public function getPopularCourses(int $limit = 10): Collection
    {
        return $this->courseRepository->getPopularCourses($limit);
    }

    public function getFeaturedCourses(int $limit = 10): Collection
    {
        return $this->courseRepository->getFeaturedCourses($limit);
    }

    public function getNewCourses(int $limit = 10): Collection
    {
        return $this->courseRepository->getNewCourses($limit);
    }

    public function getRecommendedCourses(int $userId, int $limit = 10): Collection
    {
        return $this->courseRepository->getRecommendedCourses($userId, $limit);
    }

    public function createCourse(array $data): Course
    {
        return $this->courseRepository->createCourse($data);
    }

    public function updateCourse(Course $course, array $data): Course
    {
        return $this->courseRepository->updateCourse($course, $data);
    }

    public function deleteCourse(Course $course): void
    {
        $this->courseRepository->deleteCourse($course);
    }

    public function extendCourse(Course $course, User $user, string $extensionType): array
    {
        $result = $this->courseRepository->extendCourse($course, $user, $extensionType);

        $user->notify(new CourseExtendedNotification($course, $result['new_expiry_date'], $result['cost']));

        return $result;
    }

    public function getCoursesByCategory(string $categorySlug): ?array
    {
        return $this->courseRepository->getCoursesByCategory($categorySlug);
    }

    /**
     * Fetch a course for editing.
     *
     * @param int $courseId
     * @return Course
     */
    public function getCourseForEdit(int $courseId): Course
    {
        return $this->courseRepository->getCourseForEdit($courseId);
    }

    /**
     * Update a course with optional file uploads.
     *
     * @param Course $course
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return Course
     */
    public function updateCourseWithFiles(Course $course, array $data, array $files): Course
    {
        return $this->courseRepository->updateCourseWithFiles($course, $data, $files);
    }

    /**
     * Fetch all courses for a given user with related data.
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserCourses(int $userId): Collection
    {
        return $this->courseRepository->getUserCourses($userId);
    }

        /**
     * Create a course with optional file uploads.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     * @return Course
     */
    public function createCourseWithFiles(array $data, array $files): Course
    {
        return $this->courseRepository->createCourseWithFiles($data, $files);
    }
}