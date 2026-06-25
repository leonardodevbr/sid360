<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSaleLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.edit') ?? false;
    }

    /**
     * Normaliza o legado `lot_id` (1 lote) para `lot_ids`, no mesmo padrão
     * de StoreSaleRequest.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('lot_ids') && $this->filled('lot_id')) {
            $this->merge(['lot_ids' => [$this->input('lot_id')]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lot_ids' => ['required', 'array', 'min:1'],
            'lot_ids.*' => ['integer', 'distinct', 'exists:lots,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lot_ids.required' => 'Selecione ao menos um lote.',
            'lot_ids.*.exists' => 'Um dos lotes selecionados não foi encontrado.',
        ];
    }
}
