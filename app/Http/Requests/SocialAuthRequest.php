<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:google,facebook'],
            'access_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'The provider field is required.',
            'provider.in' => 'The provider must be either google or facebook.',
            'access_token.required' => 'The access token is required.',
            'access_token.string' => 'The access token must be a string.',
        ];
    }
}