<?php

namespace App\Interfaces;

use Illuminate\Http\UploadedFile;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;

interface VideoInterface
{
    public function uploadVideo(UploadedFile $file, string $title, ?int $courseId = null, bool $isIntro = false): Video;
    public function getAllVideos(): Collection;
    public function getVideoUrl(int $id): string;
    public function getThumbnailUrl(int $id): string;
}