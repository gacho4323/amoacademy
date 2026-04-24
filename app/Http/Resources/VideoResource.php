<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'author_id' => $this->author_id,
            'title' => $this->title,
            'path' => $this->path,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'order' => $this->order,
            'is_intro' => $this->is_intro,
            'intro_duration' => $this->intro_duration,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
