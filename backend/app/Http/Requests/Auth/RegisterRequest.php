<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * CLAUDE.md §3/§13: all input is validated at the API boundary via a
 * schema, never trusted directly from the request body. Only ->validated()
 * fields ever reach a controller/model - anything else in the payload is
 * silently dropped, never persisted.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // registration is public by design
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ];
    }
}
