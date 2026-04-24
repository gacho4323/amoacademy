<?php

namespace App\Services;

use App\Interfaces\TemplateInterface;
use App\Models\Template;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class TemplateService
{
    private TemplateInterface $templateRepository;

    public function __construct(TemplateInterface $templateRepository)
    {
        $this->templateRepository = $templateRepository;
    }

    public function uploadTemplate(UploadedFile $file, string $title, int $courseId): Template
    {
        return $this->templateRepository->uploadTemplate($file, $title, $courseId);
    }

    public function getAllTemplates(): Collection
    {
        return $this->templateRepository->getAllTemplates();
    }

    public function getTemplateUrl(int $id): string
    {
        return $this->templateRepository->getTemplateUrl($id);
    }

    public function getThumbnailUrl(int $id): string
    {
        return $this->templateRepository->getThumbnailUrl($id);
    }
}