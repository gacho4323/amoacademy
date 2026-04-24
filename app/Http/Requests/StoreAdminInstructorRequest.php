<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminInstructorRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust based on your authorization logic (e.g., check if user is admin)
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:authors,email',
            'bio' => 'nullable|string',
            'trailer_video' => 'nullable|file|mimes:mp4,avi,mov|max:1024000', // 1000MB in KB (1024 * 50)
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Ime predavača je obavezno.',
            'email.required' => 'Email je obavezan.',
            'email.email' => 'Unesite validan email.',
            'email.unique' => 'Ovaj email je već u upotrebi.',
            'trailer_video.max' => 'Trailer video ne sme biti veći od 1000MB.',
            'trailer_video.mimes' => 'Trailer video mora biti u formatu MP4, AVI ili MOV.',
        ];
    }
}