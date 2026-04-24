<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TemplateUploadRequest extends FormRequest
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
            'template' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm',
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
            'template.required' => 'A template file is required',
            'template.file' => 'The uploaded file must be a valid file',
            'template.mimetypes' => 'The template must be one of the supported types: MP4, MOV, AVI, FLV, or WEBM',
            'template.max' => 'The template file must not exceed 1GB in size',
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