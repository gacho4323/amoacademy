<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InstructorResource extends JsonResource
{
    public function toArray($request) : array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'bio' => $this->bio,
            'trailer_video' => $this->trailer_video,
            'image' => $this->image,
            'courses' => CourseResource::collection($this->courses),
            'ebooks' => EbookResource::collection($this->ebooks)
        ];
    }
}
