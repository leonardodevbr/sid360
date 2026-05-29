<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSaleCarneRequest extends FormRequest
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
            'first_due_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_due_date.after' => 'A 1ª parcela do carnê deve vencer após hoje.',
        ];
    }
}
