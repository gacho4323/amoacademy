<?php

namespace App\Services;

use App\Interfaces\EbookInterface;
use App\Models\Ebook;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class EbookService
{
    private EbookInterface $ebookRepository;

    public function __construct(EbookInterface $ebookRepository)
    {
        $this->ebookRepository = $ebookRepository;
    }

    public function uploadEbook(UploadedFile $file, string $title, int $courseId): Ebook
    {
        return $this->ebookRepository->uploadEbook($file, $title, $courseId);
    }

    public function getAllEbooks(): Collection
    {
        return $this->ebookRepository->getAllEbooks();
    }

    public function getEbookUrl(int $id): string
    {
        return $this->ebookRepository->getEbookUrl($id);
    }

    public function getThumbnailUrl(int $id): string
    {
        return $this->ebookRepository->getThumbnailUrl($id);
    }
}