<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.create') ?? false;
    }

    protected function isCashSale(): bool
    {
        return (int) $this->input('installments_count', 0) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cashSale = $this->isCashSale();

        return [
            'lot_id' => ['required', 'integer', 'exists:lots,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'sale_date' => ['required', 'date'],
            'total_value' => ['required', 'integer', 'min:1'],
            'cash_value' => [
                Rule::requiredIf($cashSale),
                'nullable',
                'integer',
                Rule::when($cashSale, ['min:1', 'lte:total_value'], ['min:0']),
            ],
            'discount_amount' => ['nullable', 'integer', 'min:0', 'lte:total_value'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'down_payment' => ['required', 'integer', 'min:0'],
            'financed_value' => ['required', 'integer', 'min:0'],
            'installments_count' => [
                $cashSale ? 'nullable' : 'required',
                'integer',
                $cashSale ? 'min:0' : 'min:1',
            ],
            'installment_value' => ['required', 'integer', 'min:0'],
            'first_due_date' => [$cashSale ? 'nullable' : 'required', 'date'],
            'payment_day' => ['required', 'integer', 'min:1', 'max:31'],
            'status' => ['nullable', Rule::in(Sale::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
