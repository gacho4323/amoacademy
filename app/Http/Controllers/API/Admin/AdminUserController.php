<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
                'next_page_url' => $users->nextPageUrl(),
                'prev_page_url' => $users->previousPageUrl() 
        ]]);
}

    public function edit(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getUserById($id);
            $userCourses = $this->userService->getUserCourses($id);
            $allCourses = $this->userService->getAvailableCourses();
            $assignedCourseIds = $userCourses->pluck('id')->map(fn($id) => (string) $id)->toArray();
            Log::info('User fetched for editing', ['user_id' => $id]);
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'all_courses' => $allCourses->map(fn($course) => [
                    'id' => (string) $course->id,
                    'title' => $this->sanitizeString($course->title ?? 'Unknown'),
                    'description' => $this->sanitizeString($course->description ?? '')
                ]),
                'assigned_course_ids' => $assignedCourseIds
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user for editing', [
                'user_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error_details' => env('APP_DEBUG', false) ? $e->getMessage() : 'Invalid character encoding in user data'
            ], 500);
        }
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            // Sanitize input data
            $validatedData['name'] = $this->sanitizeString($validatedData['name'] ?? 'Unknown');
            $validatedData['first_name'] = $this->sanitizeString($validatedData['first_name'] ?? '');
            $validatedData['last_name'] = $this->sanitizeString($validatedData['last_name'] ?? '');
            $user = $this->userService->createUser($validatedData);
            Log::info('User created successfully', ['user_id' => $user->id, 'email' => $user->email]);
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => 'User created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error_details' => env('APP_DEBUG', false) ? $e->getMessage() : 'Invalid character encoding in user data'
            ], 500);
        }
    }

    public function update(UpdateAdminUserRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            // Sanitize input data
            $validatedData['name'] = $this->sanitizeString($validatedData['name'] ?? 'Unknown');
            $validatedData['first_name'] = $this->sanitizeString($validatedData['first_name'] ?? '');
            $validatedData['last_name'] = $this->sanitizeString($validatedData['last_name'] ?? '');
            $user = $this->userService->updateUser($id, $validatedData);
            Log::info('User updated successfully', ['user_id' => $id, 'email' => $user->email]);
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => 'User updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update user', [
                'user_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error_details' => env('APP_DEBUG', false) ? $e->getMessage() : 'Invalid character encoding in user data'
            ], 500);
        }
    }

    public function getAvailableCourses(): JsonResponse
    {
        try {
            $courses = $this->userService->getAvailableCourses();
            Log::info('Courses fetched successfully', ['count' => $courses->count()]);
            return response()->json([
                'success' => true,
                'data' => $courses->map(fn($course) => [
                    'id' => (string) $course->id,
                    'title' => $this->sanitizeString($course->title ?? 'Unknown')
                ])
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            Log::error('Failed to fetch courses', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch courses',
                'error_details' => env('APP_DEBUG', false) ? $e->getMessage() : 'Invalid character encoding in course data'
            ], 500);
        }
    }

    /**
     * Sanitize a string to ensure valid UTF-8 encoding.
     *
     * @param string $input
     * @return string
     */
    private function sanitizeString($input)
    {
        if (!mb_check_encoding($input, 'UTF-8')) {
            return mb_convert_encoding($input, 'UTF-8', 'auto');
        }
        return $input;
    }
}