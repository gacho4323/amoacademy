<?php

namespace App\Http\Controllers\API\Admin;

use App\Exceptions\CourseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUpdateCourseRequest;
use App\Http\Requests\AdminStoreCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminCourseController extends Controller
{
    private CourseService $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    /**
     * Fetch a course for editing.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function edit(int $id): JsonResponse
    {
        try {
            $course = $this->courseService->getCourseForEdit($id);
            return response()->json([
                'success' => true,
                'data' => new CourseResource($course),
            ]);
        } catch (CourseException $e) {
            Log::error('Failed to fetch course for editing: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            Log::error('Unexpected error fetching course for editing: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error occurred while fetching course for editing',
            ], 500);
        }
    }

    /**
     * Update a course.
     *
     * @param Request $request
     * @param Course $course
     * @return JsonResponse
     */
    public function update(AdminUpdateCourseRequest $request, Course $course): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $files = [];
            if ($request->hasFile('ebook')) {
                $files['ebook'] = $request->file('ebook');
            }
            if ($request->hasFile('template')) {
                $files['template'] = $request->file('template');
            }
            
            // Remove author_id from validated data if not provided
            if (!isset($validated['author_id']) || empty($validated['author_id'])) {
                unset($validated['author_id']);
            }
            
            $course = $this->courseService->updateCourseWithFiles($course, $validated, $files);
            
            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully',
                'data' => new CourseResource($course),
            ]);
        } catch (CourseException $e) {
            Log::error('Failed to update course: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            Log::error('Unexpected error updating course: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error occurred while updating course',
            ], 500);
        }
    }

        /**
     * Store a new course.
     *
     * @param AdminStoreCourseRequest $request
     * @return JsonResponse
     */
    public function store(AdminStoreCourseRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $files = [];
            if ($request->hasFile('ebook')) {
                $files['ebook'] = $request->file('ebook');
            }
            if ($request->hasFile('template')) {
                $files['template'] = $request->file('template');
            }

            // Remove author_id from validated data if not provided
            if (!isset($validated['author_id']) || empty($validated['author_id'])) {
                unset($validated['author_id']);
            }

            $course = $this->courseService->createCourseWithFiles($validated, $files);

            return response()->json([
                'success' => true,
                'message' => 'Course created successfully',
                'data' => new CourseResource($course),
            ], 201);
        } catch (CourseException $e) {
            Log::error('Failed to create course: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            Log::error('Unexpected error creating course: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error occurred while creating course',
            ], 500);
        }
    }
}