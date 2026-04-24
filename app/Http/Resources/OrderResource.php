<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'buyer_name' => $this->user ? $this->user->name : 'N/A',
            'status' => $this->payment_status ?? 'N/A',
            'total_amount' => $this->payment_amount ? number_format($this->payment_amount, 2) . ' ' . ($this->payment_currency ?? 'RSD') : 'N/A',
            'purchased_at' => $this->purchased_at ? $this->purchased_at->toDateTimeString() : null,
            'course_title' => $this->course ? $this->course->title : 'N/A',
        ];
    }
}