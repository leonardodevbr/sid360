<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) ($this->route('id') ?? $this->route('user'));

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'matricula' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('users', 'matricula')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::defaults()],
            'roles' => ['sometimes', 'array', 'min:1'],
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
