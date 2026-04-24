<?php
namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateUploadRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Author;
use App\Models\Course;
use App\Models\Template;
use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AdminTemplateUploadController extends Controller
{
    private TemplateService $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    public function uploadTemplate(Course $course, TemplateUploadRequest $request): JsonResponse
    {
        $template = $this->templateService->uploadTemplate(
            $request->file('template'),
            $request->title(),
            $course->id,
        );

        return response()->json(new TemplateResource($template), 201);
    }

    public function index(Course $course): JsonResponse
    {
        $templates = $course->templates()->paginate(10);

        return response()->json(TemplateResource::collection($templates));
    }

    public function indexAll(): JsonResponse
    {
        $templates = Template::with(['author', 'course.category'])->paginate(10);
        return response()->json(TemplateResource::collection($templates));
    }

    public function getTemplateUrl(Course $course, Template $template): JsonResponse
    {
        $this->authorizeTemplate($course, $template);
        $url = Storage::disk('r2')->temporaryUrl($template->path, now()->addMinutes(30));

        return response()->json(['url' => $url]);
    }

    public function getThumbnailUrl(Course $course, Template $template): JsonResponse
    {
        $this->authorizeTemplate($course, $template);
        $thumbnailPath = 'thumbnails/' . pathinfo($template->path, PATHINFO_FILENAME) . '.jpg';

        $url = Storage::disk('r2')->exists($thumbnailPath)
        ? Storage::disk('r2')->temporaryUrl($thumbnailPath, now()->addMinutes(30))
        : Storage::disk('r2')->temporaryUrl($template->path, now()->addMinutes(30));

        return response()->json(['url' => $url]);
    }

    public function show(Course $course, Template $template): JsonResponse
    {
        $this->authorizeTemplate($course, $template);
        $url = Storage::disk('r2')->temporaryUrl($template->path, now()->addMinutes(30));

        $template->loadMissing('author');

        return response()->json([
            'url'      => $url,
            'course'   => $course,
            'template' => $template,
        ]);
    }

    private function authorizeTemplate(Course $course, Template $template): void
    {
        if ($template->course_id !== $course->id) {
            abort(403, 'Ebook does not belong to this course');
        }
    }
}
