<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EbookUploadRequest extends FormRequest
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
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ebook' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.presentationml.presentation,application/pdf',
                'max:1048576', // 1GB in kilobytes
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'ebook.required' => 'A ebook file is required',
            'ebook.file' => 'The uploaded file must be a valid file',
            'ebook.mimetypes' => 'The ebook must be in PDF format',
            'ebook.max' => 'The ebook file must not exceed 1GB in size',
            'title.required' => 'A title is required',
            'title.max' => 'The title must not exceed 255 characters',
        ];
    }

    /**
     * Get the validated title.
     */
    public function title(): string
    {
        return $this->input('title');
    }
}
