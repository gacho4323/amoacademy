<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'type' => 'sometimes|string|in:professional,lifestyle',
            'category_id' => ['sometimes'],
            'author_id' => ['sometimes'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'original_price' => ['sometimes', 'numeric', 'min:0'],
            'language' => ['sometimes', 'array', 'min:1'],
            'language.*' => [Rule::in(config('courses.languages'))],
            'audio_language' => ['sometimes', 'array', 'min:1'],
            'audio_language.*' => [Rule::in(config('courses.languages'))],
            'ebook' => ['nullable', 'file', 'mimes:pdf', 'max:' . config('courses.files.max_size.ebook')],
            'template' => ['nullable', 'file', 'max:' . config('courses.files.max_size.template')],
        ];
    }
}
