<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company.name' => ['required', 'string', 'max:150'],
            'company.document' => ['nullable', 'string', 'max:20', 'unique:companies,document'],
            'company.email' => ['nullable', 'email', 'max:150'],
            'company.phone' => ['nullable', 'string', 'max:20'],

            'user.name' => ['required', 'string', 'max:150'],
            'user.email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'user.password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
