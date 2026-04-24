<?php

namespace App\Repositories;

use App\Exceptions\CourseException;
use App\Interfaces\CourseInterface;
use App\Models\Category;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CourseRepository implements CourseInterface
{
    public function getAllCourses(): LengthAwarePaginator
    {
        $perPage = request()->input('per_page', 10);

        $query = Course::with([
            'author',
            'category',  
            'videos' => function ($q) {
                $q->orderBy('order');
            },
            'ebooks',
            'templates'
        ]);

        return $query->paginate($perPage);
    }

    public function getPopularCourses(int $limit = 10): Collection
    {
        $cacheKey = 'popular_courses_' . $limit;

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($limit) {
            return Course::with(['author', 'category', 'videos'])
                ->leftJoin('course_user', 'courses.id', '=', 'course_user.course_id')
                ->groupBy([
                    'courses.id',
                    'courses.author_id',
                    'courses.category_id',
                    'courses.title',
                    'courses.description',
                    'courses.price',
                    'courses.is_featured',
                    'courses.created_at',
                    'courses.updated_at',
                ])
                ->select([
                    'courses.id',
                    'courses.author_id',
                    'courses.category_id',
                    'courses.title',
                    'courses.description',
                    'courses.price',
                    'courses.is_featured',
                    'courses.created_at',
                    'courses.updated_at',
                ])
                ->orderByRaw('COUNT(course_user.user_id) DESC, courses.created_at DESC')
                ->take($limit)
                ->get();
        });
    }

    public function getFeaturedCourses(int $limit = 10): Collection
    {
        $cacheKey = 'featured_courses_' . $limit;

        return Course::with(['author', 'category', 'videos'])
            ->where('is_featured', 1)
            ->take($limit)
            ->get();
    }

    public function getNewCourses(int $limit = 10): Collection
    {
        $cacheKey = 'new_courses_' . $limit;

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($limit) {
            return Course::with(['author', 'category', 'videos'])
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();
        });
    }

    public function getRecommendedCourses(int $userId, int $limit = 10): Collection
    {
        $cacheKey = 'recommended_courses_user_' . $userId . '_' . $limit;

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($userId, $limit) {
            $userCategories = Course::whereHas('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->pluck('category_id')->unique();

            if ($userCategories->isEmpty()) {
                return Course::with(['author', 'category', 'videos'])
                    ->orderBy('price', 'asc')
                    ->take($limit)
                    ->get();
            }

            return Course::with(['author', 'category', 'videos'])
                ->whereIn('category_id', $userCategories)
                ->whereDoesntHave('users', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->inRandomOrder()
                ->take($limit)
                ->get();
        });
    }

    public function createCourse(array $data): Course
    {
        try {
            return Course::create($data);
        } catch (\Exception $e) {
            throw new CourseException("Failed to create course: " . $e->getMessage());
        }
    }

    public function updateCourse(Course $course, array $data): Course
    {
        try {
            $course->update($data);
            Cache::forget('popular_courses_10');
            Cache::forget('featured_courses_10');
            Cache::forget('new_courses_10');

            return $course->fresh();
        } catch (\Exception $e) {
            throw new CourseException("Failed to update course: " . $e->getMessage());
        }
    }

    public function deleteCourse(Course $course): void
    {
        try {
            $course->delete();
            Cache::forget('popular_courses_10');
            Cache::forget('featured_courses_10');
            Cache::forget('new_courses_10');
        } catch (\Exception $e) {
            throw new CourseException("Failed to delete course: " . $e->getMessage());
        }
    }

    public function extendCourse(Course $course, User $user, string $extensionType): array
    {
        $pivot = $course->users()->where('user_id', $user->id)->first()->pivot;
        $currentExpiry = $pivot->course_expiry_date ? \Carbon\Carbon::parse($pivot->course_expiry_date) : now();
        $newExpiry = null;
        $cost = 0;

        if ($extensionType === 'free_1_month') {
            $newExpiry = $currentExpiry->addMonth();
        } elseif ($extensionType === 'discounted_6_months') {
            $newExpiry = $currentExpiry->addMonths(6);
            $cost = $course->price * 6 * 0.7; // 30% discount
        } else {
            throw new CourseException('Invalid extension type', 400);
        }

        $course->users()->updateExistingPivot($user->id, ['course_expiry_date' => $newExpiry]);

        return [
            'new_expiry_date' => $newExpiry->toDateString(),
            'cost' => $cost,
        ];
    }

    public function getCoursesByCategory(string $categorySlug): ?array
    {
        $category = Category::where('slug', $categorySlug)->first();

        if (!$category) {
            return null;
        }

        $courses = Course::with(['author', 'category', 'videos'])
            ->where('category_id', $category->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'courses' => $courses,
            'categoryName' => $category->name,
            'categorySlug' => $category->slug,
        ];
    }

    public function getCourseForEdit(int $courseId): Course
    {
        try {
            $course = Course::with(['author', 'category', 'ebooks', 'templates'])
                ->findOrFail($courseId);

            return $course;
        } catch (\Exception $e) {
            throw new CourseException("Failed to fetch course for editing: " . $e->getMessage(), 404);
        }
    }

    public function updateCourseWithFiles(Course $course, array $data, array $files): Course
    {
        try {
            // Update course data
            $course->update($data);

            // Handle E-book file upload
            if (isset($files['ebook'])) {
                $ebookFile = $files['ebook'];
                $maxSize = config('courses.files.max_size.ebook'); // In KB

                if ($ebookFile->getSize() / 1024 > $maxSize) {
                    throw new CourseException("E-book file exceeds maximum size of {$maxSize}KB", 400);
                }

                if (!$ebookFile->isValid()) {
                    throw new CourseException("Invalid E-book file uploaded", 400);
                }

                $path = $ebookFile->store('ebooks', 'public');
                $course->ebooks()->create([
                    'author_id' => $course->author_id,
                    'title' => $data['title'] . ' E-book',
                    'path' => $path,
                    'size' => $ebookFile->getSize(),
                    'mime_type' => $ebookFile->getMimeType(),
                ]);
            }

            // Handle Template file upload
            if (isset($files['template'])) {
                $templateFile = $files['template'];
                $maxSize = config('courses.files.max_size.template'); // In KB

                if ($templateFile->getSize() / 1024 > $maxSize) {
                    throw new CourseException("Template file exceeds maximum size of {$maxSize}KB", 400);
                }

                if (!$templateFile->isValid()) {
                    throw new CourseException("Invalid Template file uploaded", 400);
                }

                $path = $templateFile->store('templates', 'public');
                $course->templates()->create([
                    'author_id' => $course->author_id,
                    'title' => $data['title'] . ' Template',
                    'path' => $path,
                    'size' => $templateFile->getSize(),
                    'mime_type' => $templateFile->getMimeType(),
                ]);
            }

            // Clear cache
            Cache::forget('popular_courses_10');
            Cache::forget('featured_courses_10');
            Cache::forget('new_courses_10');

            return $course->fresh()->load(['author', 'category', 'ebooks', 'templates']);
        } catch (\Exception $e) {
            throw new CourseException("Failed to update course with files: " . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch all courses for a given user with related data.
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserCourses(int $userId): Collection
    {
        try {
            $cacheKey = 'user_courses_' . $userId;

            return Cache::remember($cacheKey, now()->addHours(24), function () use ($userId) {
                return Course::whereHas('users', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                    ->with([
                        'author',
                        'category',
                        'videos' => function ($q) {
                            $q->orderBy('order');
                        },
                        'ebooks',
                        'templates',
                        'users' => function ($query) use ($userId) {
                            $query->where('user_id', $userId)
                                ->select('users.id', 'users.name', 'course_user.course_expiry_date', 'course_user.purchased_at');
                        },
                        'payments' => function ($query) use ($userId) {
                            $query->where('user_id', $userId)
                                ->where('status', 'completed')
                                ->select('id', 'course_id', 'user_id', 'amount', 'payment_gateway', 'transaction_id');
                        },
                    ])
                    ->get();
            });
        } catch (\Exception $e) {
            throw new CourseException("Failed to fetch user courses: " . $e->getMessage(), 500);
        }
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
        try {
            // Create the course
            $course = Course::create($data);

            // Handle E-book file upload
            if (isset($files['ebook'])) {
                $ebookFile = $files['ebook'];
                $maxSize = config('courses.files.max_size.ebook'); // In KB

                if ($ebookFile->getSize() / 1024 > $maxSize) {
                    throw new CourseException("E-book file exceeds maximum size of {$maxSize}KB", 400);
                }

                if (!$ebookFile->isValid()) {
                    throw new CourseException("Invalid E-book file uploaded", 400);
                }

                $path = $ebookFile->store('ebooks', 'public');
                $course->ebooks()->create([
                    'author_id' => $course->author_id ?? null,
                    'title' => $data['title'] . ' E-book',
                    'path' => $path,
                    'size' => $ebookFile->getSize(),
                    'mime_type' => $ebookFile->getMimeType(),
                ]);
            }

            // Handle Template file upload
            if (isset($files['template'])) {
                $templateFile = $files['template'];
                $maxSize = config('courses.files.max_size.template'); // In KB

                if ($templateFile->getSize() / 1024 > $maxSize) {
                    throw new CourseException("Template file exceeds maximum size of {$maxSize}KB", 400);
                }

                if (!$templateFile->isValid()) {
                    throw new CourseException("Invalid Template file uploaded", 400);
                }

                $path = $templateFile->store('templates', 'public');
                $course->templates()->create([
                    'author_id' => $course->author_id ?? null,
                    'title' => $data['title'] . ' Template',
                    'path' => $path,
                    'size' => $templateFile->getSize(),
                    'mime_type' => $templateFile->getMimeType(),
                ]);
            }

            // Clear cache
            Cache::forget('popular_courses_10');
            Cache::forget('featured_courses_10');
            Cache::forget('new_courses_10');

            return $course->fresh()->load(['author', 'category', 'ebooks', 'templates']);
        } catch (\Exception $e) {
            throw new CourseException("Failed to create course with files: " . $e->getMessage(), 500);
        }
    }
}
