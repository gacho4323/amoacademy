<?php

namespace App\Http\Controllers\API\Video;

use App\Http\Controllers\Controller;
use App\Http\Requests\VideoUploadRequest;
use App\Http\Resources\VideoResource;
use App\Models\Author;
use App\Models\Course;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;


class VideoController extends Controller
{
    private VideoService $videoService;

    public function __construct(VideoService $videoService)
    {
        $this->videoService = $videoService;
    }

    public function store(Course $course, Author $author, VideoUploadRequest $request): JsonResponse
    {
        $isIntro = $request->has('is_intro') && $request->boolean('is_intro');
        $video = $this->videoService->uploadVideo(
            $request->file('video'),
            $request->title(),
            $course->id,
            $author->id,
            $isIntro
        );

        return response()->json(new VideoResource($video), 201);
    }

    public function concatenate(Course $course, Video $introVideo, Video $mainVideo): JsonResponse
    {
        $this->authorizeVideo($course, $introVideo);
        $this->authorizeVideo($course, $mainVideo);

        if (!$introVideo->is_intro) {
            abort(400, 'The first video must be marked as an intro');
        }

        $concatenatedVideo = $this->videoService->concatenateVideos($introVideo, $mainVideo);

        return response()->json(new VideoResource($concatenatedVideo), 201);
    }

    public function index(Course $course): JsonResponse
    {
        $videos = $course->videos()->paginate(60);

        return response()->json(VideoResource::collection($videos));
    }

    public function getVideoUrl(Course $course, Video $video): JsonResponse
    {
        $this->authorizeVideo($course, $video);
        $url = Storage::disk('r2')->temporaryUrl($video->path, now()->addMinutes(30));

        return response()->json([
            'url' => $url,
            'intro_duration' => $video->intro_duration,
        ]);
    }

    public function getThumbnailUrl(Course $course, Video $video): JsonResponse
    {
        $this->authorizeVideo($course, $video);
        $thumbnailPath = 'thumbnails/' . pathinfo($video->path, PATHINFO_FILENAME) . '.jpg';

        $url = Storage::disk('r2')->exists($thumbnailPath)
            ? Storage::disk('r2')->temporaryUrl($thumbnailPath, now()->addMinutes(30))
            : Storage::disk('r2')->temporaryUrl($video->path, now()->addMinutes(30));

        return response()->json(['url' => $url]);
    }

    private function authorizeVideo(Course $course, Video $video): void
    {
        if ($video->course_id !== $course->id) {
            abort(403, 'Video does not belong to this course');
        }
    }
}
