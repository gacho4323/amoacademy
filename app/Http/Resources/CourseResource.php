<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = Auth::user();
        $courseExpiryDate = null;

        if ($user && $this->users->contains($user->id)) {
            $courseExpiryDate = $this->users->find($user->id)->pivot->course_expiry_date;
        }

        // Safely handle the payments relationship
        $payment = $this->whenLoaded('payments', function () {
            return $this->payments->isNotEmpty() ? $this->payments->first() : null;
        }, null); // Ensure default is null if payments is not loaded

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
            'price' => $this->price,
            'item_code' => $this->item_code,
            'original_price' => $this->original_price,
            'is_featured' => $this->is_featured,
            'course_expiry_date' => $courseExpiryDate ? \Carbon\Carbon::parse($courseExpiryDate)->format('Y-m-d') : null,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ],
            'language' => $this->language,
            'audio_language' => $this->audio_language,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'videos' => VideoResource::collection($this->whenLoaded('videos')),
            'ebooks' => EbookResource::collection($this->whenLoaded('ebooks')),
            'templates' => TemplateResource::collection($this->whenLoaded('templates')),
            'created_at' => $this->whenPivotLoaded('course_user', function () {
                return $this->pivot->purchased_at ? $this->pivot->purchased_at->format('Y-m-d') : null;
            }),
            'updated_at' => $this->updated_at,
            'orderNumber' => $payment instanceof \App\Models\Payment ? $payment->transaction_id : null,
            'paymentMethod' => $payment instanceof \App\Models\Payment ? $payment->payment_gateway : null,
        ];
    }

    /**
     * Customize the response structure for collections.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        if ($this->resource instanceof \Illuminate\Database\Eloquent\Collection) {
            $response->setData(['courses' => $this->collection]);
        }
    }
}
