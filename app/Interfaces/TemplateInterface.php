<?php

namespace App\Interfaces;

use App\Models\Template;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

interface TemplateInterface
{
	public function uploadTemplate(UploadedFile $file, string $title, ?int $courseId = null): Template;
    public function getAllTemplates(): Collection;
    public function getTemplateUrl(int $id): string;
    public function getThumbnailUrl(int $id): string;
}