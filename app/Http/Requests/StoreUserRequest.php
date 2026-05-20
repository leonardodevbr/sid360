<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'matricula' => ['nullable', 'string', 'max:50', 'unique:users,matricula'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'in:admin,super-admin'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => 'nome de usuário',
            'matricula' => 'matrícula',
        ];
    }
}
