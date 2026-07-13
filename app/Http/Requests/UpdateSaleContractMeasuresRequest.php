<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleContractMeasuresRequest extends FormRequest
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
            'contract_lot_measures' => ['nullable', 'array'],
            'contract_lot_measures.*' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
