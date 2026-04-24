<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EbookUploadRequest;
use App\Http\Resources\EbookResource;
use App\Models\Course;
use App\Models\Ebook;
use App\Services\EbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AdminEbookUploadController extends Controller
{
    private EbookService $ebookService;

    public function __construct(EbookService $ebookService)
    {
        $this->ebookService = $ebookService;
    }

    public function uploadEbook(Course $course, EbookUploadRequest $request): JsonResponse
    {
        $ebook = $this->ebookService->uploadEbook(
            $request->file('ebook'),
            $request->title(),
            $course->id
        );

        return response()->json(new EbookResource($ebook), 201);
    }

    public function index(Course $course): JsonResponse
    {
        $ebooks = $course->ebooks()->paginate(10);

        return response()->json(EbookResource::collection($ebooks));
    }

    public function indexAll(): JsonResponse
    {
        $ebooks = Ebook::with(['course.category'])->paginate(10);
        return response()->json(EbookResource::collection($ebooks));
    }

    public function getThumbnailUrl(Course $course, Ebook $ebook): JsonResponse
    {
        $this->authorizeEbook($course, $ebook);
        $thumbnailPath = 'thumbnails/' . pathinfo($ebook->path, PATHINFO_FILENAME) . '.jpg';

        $url = Storage::disk('r2')->exists($thumbnailPath)
            ? Storage::disk('r2')->temporaryUrl($thumbnailPath, now()->addMinutes(30))
            : Storage::disk('r2')->temporaryUrl($ebook->path, now()->addMinutes(30));

        return response()->json(['url' => $url]);
    }

    public function show(Course $course, Ebook $ebook): JsonResponse
    {
        $url = Storage::disk('r2')->temporaryUrl($ebook->path, now()->addMinutes(30));

        return response()->json([
            'url'    => $url,
            'course' => $course,
            'ebook'  => $ebook,
        ]);
    }

    private function authorizeEbook(Course $course, Ebook $ebook): void
    {
        if ($ebook->course_id !== $course->id) {
            abort(403, 'Ebook does not belong to this course');
        }
    }
}