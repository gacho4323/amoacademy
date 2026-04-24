<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->role === 'admin';
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,user',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Ime je obavezno.',
            'email.required' => 'Email je obavezan.',
            'email.email' => 'Unesite validnu email adresu.',
            'email.unique' => 'Email je već u upotrebi.',
            'password.required' => 'Lozinka je obavezna.',
            'password.min' => 'Lozinka mora imati najmanje 8 karaktera.',
            'role.sometimes' => 'Uloga je obavezna.',
            'role.in' => 'Uloga mora biti admin, ili obican.',
        ];
    }
}
