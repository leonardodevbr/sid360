<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'cpf' => ['sometimes', 'string', 'max:20', Rule::unique('clients', 'cpf')->ignore($this->route('id'))],
            'rg' => ['nullable', 'string', 'max:30'],
            'rg_issuer' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'profession' => ['nullable', 'string', 'max:120'],
            'marital_status' => ['nullable', 'string', Rule::in(Client::MARITAL_STATUSES)],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_status' => ['nullable', 'string', Rule::in(['confirmed', 'none'])],
            'email' => ['nullable', 'email', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:2'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
