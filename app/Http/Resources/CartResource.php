<?php

namespace App\Http\Resources;

use App\Http\Resources\CourseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'courses' => CourseResource::collection($this->whenLoaded('courses')),
            'total_price' => $this->courses->sum('price'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}