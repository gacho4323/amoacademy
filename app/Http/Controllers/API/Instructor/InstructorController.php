<?php
namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstructorResource;
use App\Models\Author;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InstructorController extends Controller
{
    private InstructorService $instructorService;

    public function __construct(InstructorService $instructorService)
    {
        $this->instructorService = $instructorService;
    }

    public function index(): JsonResponse
    {
        $authors = Author::with(['courses', 'courses.category'])->paginate(10);
        return response()->json(InstructorResource::collection($authors));
    }

    public function show(int $instructor): JsonResponse
    {
        try {
            $instructor = $this->instructorService->getInstructorWithDetails($instructor);
            return response()->json(new InstructorResource($instructor));
        } catch (\Exception $e) {
            Log::error('Failed to fetch instructor: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch instructor details'], 500);
        }
    }
}
