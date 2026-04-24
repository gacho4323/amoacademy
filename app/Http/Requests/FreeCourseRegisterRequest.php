<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FreeCourseRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Allow all users to make this request
    }

    public function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:255',
            //'email'   => 'required|email|max:255|unique:users,email',
            'consent' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'    => 'Ime je obavezno.',
            //'email.required'   => 'Email adresa je obavezna.',
            //'email.email'      => 'Unesite validnu email adresu.',
            //'email.unique'     => 'Ova email adresa je već registrovana.',
            'consent.required' => 'Morate prihvatiti saglasnost za obradu podataka.',
        ];
    }
}
