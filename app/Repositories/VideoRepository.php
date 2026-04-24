<?php

namespace App\Repositories;

use App\Interfaces\VideoInterface;
use App\Jobs\GenerateThumbnailVideoJob;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VideoRepository implements VideoInterface
{
    private function getTemporaryUrl(string $path): string
    {
        return Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(30));
    }

    public function uploadVideo(UploadedFile $file, string $title, int $courseId = null, bool $isIntro = false): Video
    {
        try {
            $path = $file->store('videos', 'r2');
            $video = Video::create([
                'course_id' => $courseId,
                'title' => $title,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'order' => Video::where('course_id', $courseId)->max('order') + 1,
                'is_intro' => $isIntro,
            ]);

            GenerateThumbnailVideoJob::dispatch($video);

            return $video;
        } catch (\Exception $e) {
            throw new \Exception("Failed to upload video: " . $e->getMessage());
        }
    }

    public function getAllVideos(): Collection
    {
        return Video::all();
    }

    public function getVideoUrl(int $id): string
    {
        $video = Video::findOrFail($id);
        
        return $this->getTemporaryUrl($video->path);
    }

    public function getThumbnailUrl(int $id): string
    {
        $video = Video::findOrFail($id);
        $thumbnailPath = 'thumbnails/' . pathinfo($video->path, PATHINFO_FILENAME) . '.jpg';

        if (!Storage::disk('r2')->exists($thumbnailPath)) {
            GenerateThumbnailVideoJob::dispatch($video);
        }

        return $this->getTemporaryUrl(Storage::disk('r2')->exists($thumbnailPath) ? $thumbnailPath : $video->path);
    }
}