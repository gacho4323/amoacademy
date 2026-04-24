<?php

namespace App\Repositories;

use App\Interfaces\EbookInterface;
use App\Jobs\GenerateThumbnailEbookJob;
use App\Models\Ebook;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EbookRepository implements EbookInterface
{
    private function getTemporaryUrl(string $path): string
    {
        return Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(30));
    }

    public function uploadEbook(UploadedFile $file, string $title, int $courseId = null): Ebook
    {
        try {
            $path = $file->store('ebooks', 'r2');
            $ebook = Ebook::create([
                'course_id' => $courseId,
                'title' => $title,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            GenerateThumbnailEbookJob::dispatch($ebook);
            return $ebook;
        } catch (\Exception $e) {
            throw new \Exception("Failed to upload ebook: " . $e->getMessage());
        }
    }

    public function getAllEbooks(): Collection
    {
        return Ebook::all();
    }

    public function getEbookUrl(int $id): string
    {
        $ebook = Ebook::findOrFail($id);
        return $this->getTemporaryUrl($ebook->path);
    }

    public function getThumbnailUrl(int $id): string
    {
        $ebook = Ebook::findOrFail($id);
        $thumbnailPath = 'thumbnails/' . pathinfo($ebook->path, PATHINFO_FILENAME) . '.jpg';

        if (!Storage::disk('r2')->exists($thumbnailPath)) {
            GenerateThumbnailEbookJob::dispatch($ebook);
        }

        return $this->getTemporaryUrl(Storage::disk('r2')->exists($thumbnailPath) ? $thumbnailPath : $ebook->path);
    }
}