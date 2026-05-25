<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInstallmentBoletoRequest extends FormRequest
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
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'waive_penalties' => ['sometimes', 'boolean'],
        ];
    }
}
