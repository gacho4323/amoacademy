<?php

namespace App\Repositories;

use App\Interfaces\TemplateInterface;
use App\Jobs\GenerateThumbnailTemplateJob;
use App\Models\Template;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TemplateRepository implements TemplateInterface
{
	private function getTemporaryUrl(string $path): string
    {
        return Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(30));
    }

    public function uploadTemplate(UploadedFile $file, string $title, int $courseId = null): Template
    {
        try {
            $path = $file->store('templates', 'r2');
            $template = Template::create([
                'course_id' => $courseId,
                'title' => $title,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            GenerateThumbnailTemplateJob::dispatch($template);

            return $template;
        } catch (\Exception $e) {
            throw new \Exception("Failed to upload template: " . $e->getMessage());
        }
    }

    public function getAllTemplates(): Collection
    {
        return Template::all();
    }

    public function getTemplateUrl(int $id): string
    {
        $template = Template::findOrFail($id);

        return $this->getTemporaryUrl($template->path);
    }

    public function getThumbnailUrl(int $id): string
    {
        $template = Template::findOrFail($id);
        $thumbnailPath = 'thumbnails/' . pathinfo($template->path, PATHINFO_FILENAME) . '.jpg';

        if (!Storage::disk('r2')->exists($thumbnailPath)) {
            GenerateThumbnailTemplateJob::dispatch($template);
        }

        return $this->getTemporaryUrl(Storage::disk('r2')->exists($thumbnailPath) ? $thumbnailPath : $template->path);
    }
}