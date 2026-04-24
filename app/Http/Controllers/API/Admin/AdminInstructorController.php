<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminInstructorRequest;
use App\Http\Requests\UpdateAdminInstructorRequest;
use App\Http\Resources\InstructorResource;
use App\Models\Author;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminInstructorController extends Controller
{
    private InstructorService $instructorService;

    public function __construct(InstructorService $instructorService)
    {
        $this->instructorService = $instructorService;
    }

    public function index(): JsonResponse
    {
        try {
            $authors = $this->instructorService->getAllInstructors();
            return response()->json([
                'success' => true,
                'data' => InstructorResource::collection($authors)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch authors: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch authors'], 500);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            $author = $this->instructorService->getInstructorWithDetails($id);
            return response()->json([
                'success' => true,
                'data' => new InstructorResource($author)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch author for editing: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch author details'], 500);
        }
    }

    public function store(StoreAdminInstructorRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            if ($request->hasFile('trailer_video')) {
                $validatedData['trailer_video'] = $request->file('trailer_video')->store('videos');
            }

            $author = $this->instructorService->createInstructor($validatedData);
            return response()->json([
                'success' => true,
                'data' => new InstructorResource($author),
                'message' => 'Author created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create author: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create author'], 500);
        }
    }

    public function update(UpdateAdminInstructorRequest $request, int $id): JsonResponse
    {
        try {
            $author = $this->instructorService->getInstructorWithDetails($id);

            $validatedData = $request->validated();

            if ($request->hasFile('trailer_video')) {
                $validatedData['trailer_video'] = $request->file('trailer_video')->store('videos');
            }

            $updatedAuthor = $this->instructorService->updateInstructor($id, $validatedData);

            return response()->json([
                'success' => true,
                'data' => new InstructorResource($updatedAuthor),
                'message' => 'Author updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update author: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update author'], 500);
        }
    }
}