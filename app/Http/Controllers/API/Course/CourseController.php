<?php

namespace App\Http\Controllers\API\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseExtensionRequest;
use App\Http\Requests\CourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    private CourseService $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $courses = $this->courseService->getAllCourses();
            
            return response()->json(CourseResource::collection($courses));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch courses'], 500);
        }
    }

    public function popular(): JsonResponse
    {
        try {
            $limit = request()->input('limit', 10);
            $courses = $this->courseService->getPopularCourses($limit)->load('author');
            return response()->json(CourseResource::collection($courses));
        } catch (\Exception $e) {
            \Log::error('Failed to fetch popular courses: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Failed to fetch popular courses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function featured(): JsonResponse
    {
        try {
            $limit = request()->input('limit', 10);
            $courses = $this->courseService->getFeaturedCourses($limit)->load('author');
            return response()->json(CourseResource::collection($courses));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch featured courses'], 500);
        }
    }

    public function new(): JsonResponse
    {
        try {
            $limit = request()->input('limit', 10);
            $courses = $this->courseService->getNewCourses($limit)->load('author');
            return response()->json(CourseResource::collection($courses));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch new courses'], 500);
        }
    }

    public function recommended(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $limit = request()->input('limit', 10);
            $courses = $this->courseService->getRecommendedCourses($user->id, $limit)->load('author');
            return response()->json(CourseResource::collection($courses));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch recommended courses'], 500);
        }
    }

    public function store(CourseRequest $request): JsonResponse
    {
        try {
            $course = $this->courseService->createCourse($request->validated());
            return response()->json(new CourseResource($course), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create course'], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $course = Course::find($id);
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }
            $includes = request()->input('include', '');
            $relations = explode(',', $includes);
            $allowedRelations = ['author', 'videos', 'ebooks', 'templates', 'category'];
            $relations = array_intersect($relations, $allowedRelations);
            $course->load($relations);
            return response()->json(new CourseResource($course));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch course'], 500);
        }
    }

    public function update(CourseRequest $request, Course $course): JsonResponse
    {
        try {
            $course = $this->courseService->updateCourse($course, $request->validated());
            $course->load(['author', 'videos', 'ebooks', 'templates', 'category']);
            return response()->json(new CourseResource($course));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }

    public function destroy(Course $course): JsonResponse
    {
        try {
            $this->courseService->deleteCourse($course);
            return response()->json(['message' => 'You have successfully deleted course!'], 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete course'], 500);
        }
    }

    public function extend(CourseExtensionRequest $request, Course $course): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user->courses()->where('course_id', $course->id)->exists()) {
                return response()->json(['message' => 'User is not enrolled in this course'], 403);
            }

            $result = $this->courseService->extendCourse($course, $user, $request->extension_type);

            return response()->json([
                'message' => 'Course extended successfully',
                'new_expiry_date' => $result['new_expiry_date'],
                'cost' => $result['cost'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to extend course: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to extend course'], 500);
        }
    }

   public function getCoursesByCategory(string $categorySlug): JsonResponse
   {
        try {
            $courses = $this->courseService->getCoursesByCategory($categorySlug);

            if ($courses === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategorija nije pronađena'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'courses' => CourseResource::collection($courses['courses']),
                'categoryName' => $courses['categoryName'],
                'categorySlug' => $categorySlug,
                'total' => $courses['courses']->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch courses by category: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Greška pri učitavanju kurseva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch all courses for the authenticated user.
     *
     * @return JsonResponse
     */
    public function userCourses(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $courses = $this->courseService->getUserCourses($user->id);

            return response()->json(CourseResource::collection($courses));
        } catch (\Exception $e) {
            \Log::error('Failed to fetch user courses: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Failed to fetch user courses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
