<?php

namespace App\Interfaces;

use Illuminate\Http\UploadedFile;
use App\Models\Ebook;
use Illuminate\Database\Eloquent\Collection;

interface EbookInterface
{
    public function uploadEbook(UploadedFile $file, string $title, ?int $courseId = null): Ebook;
    public function getAllEbooks(): Collection;
    public function getEbookUrl(int $id): string;
    public function getThumbnailUrl(int $id): string;
}
