<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInstallmentPixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'waive_penalties' => ['sometimes', 'boolean'],
            'expiry_seconds' => ['nullable', 'integer', 'min:3600', 'max:604800'],
        ];
    }
}
