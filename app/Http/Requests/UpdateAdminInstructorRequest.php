<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminInstructorRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust based on your authorization logic
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('authors', 'email')->ignore($this->route('id')),
            ],
            'bio' => 'nullable|string',
            'trailer_video' => 'nullable|file|mimes:mp4,avi,mov|max:1024000', // 1000MB in KB
        ];
    }

    public function messages()
    {
        return [
            'email.email' => 'Unesite validan email.',
            'email.unique' => 'Ovaj email je već u upotrebi.',
            'trailer_video.max' => 'Trailer video ne sme biti veći od 1000MB.',
            'trailer_video.mimes' => 'Trailer video mora biti u formatu MP4, AVI ili MOV.',
        ];
    }
}