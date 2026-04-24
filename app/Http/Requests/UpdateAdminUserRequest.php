<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->role === 'admin';
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('id')),
            ],
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:admin,user',
            'courses' => 'sometimes|array',
            'courses.*' => 'exists:courses,id',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Ime je obavezno.',
            'email.required' => 'Email je obavezan.',
            'email.email' => 'Unesite validnu email adresu.',
            'email.unique' => 'Email je već u upotrebi.',
            'password.min' => 'Lozinka mora imati najmanje 8 karaktera.',
            'role.required' => 'Uloga je obavezna.',
            'role.in' => 'Uloga mora biti admin ili obican.',
            'courses.array' => 'Kursevi moraju biti niz.',
            'courses.*.exists' => 'Izabrani kurs ne postoji.',
        ];
    }
}