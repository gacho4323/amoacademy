<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VideoUploadRequest extends FormRequest
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
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm',
                'max:5242880', // 5GB in kilobytes
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
            'video.required' => 'A video file is required',
            'video.file' => 'The uploaded file must be a valid file',
            'video.mimetypes' => 'The video must be one of the supported types: MP4, MOV, AVI, FLV, or WEBM',
            'video.max' => 'The video file must not exceed 1GB in size',
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
