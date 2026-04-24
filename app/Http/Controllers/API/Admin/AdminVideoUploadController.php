<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VideoUploadRequest;
use App\Http\Resources\VideoResource;
use App\Models\Author;
use App\Models\Course;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class AdminVideoUploadController extends Controller
{
    private VideoService $videoService;

    public function __construct(VideoService $videoService)
    {
        $this->videoService = $videoService;
    }

    public function uploadVideo(Course $course, VideoUploadRequest $request): JsonResponse
    {
        try {
            $isIntro = $request->boolean('is_intro', false);
            $video = $this->videoService->uploadVideo(
                $request->file('video'),
                $request->title(),
                $course->id,
                $isIntro
            );

            return response()->json([
                'message' => 'Video uploaded successfully',
                'data' => new VideoResource($video)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Video upload failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to upload video',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
