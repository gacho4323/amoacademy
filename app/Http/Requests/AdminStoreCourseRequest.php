<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminStoreCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Assuming admin middleware handles authorization
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'author_id' => ['nullable', 'exists:users,id'],
            'is_featured' => ['boolean'],
            'ebook' => ['nullable', 'file', 'mimes:pdf', 'max:' . config('courses.files.max_size.ebook')],
            'template' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:' . config('courses.files.max_size.template')],
        ];
    }
}
