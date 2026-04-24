<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EbookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'author_id' => $this->author_id,
            'category_id' => $this->course?->category_id,
            'title' => $this->title,
            'description' => $this->description,
            'path' => $this->path,
            'thumbnail' => $this->thumbnail,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'author' => $this->whenLoaded('author'),
            'course' => $this->whenLoaded('course'),
            'category' => $this->whenLoaded('course.category'), 
        ];
    }
}
