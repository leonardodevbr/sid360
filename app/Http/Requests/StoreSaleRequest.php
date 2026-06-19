<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lot;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
     * Normaliza o legado `lot_id` (1 lote) para `lot_ids` (N lotes), mantendo
     * compatibilidade com qualquer chamador que ainda envie apenas `lot_id`.
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
        $cashSale = $this->isCashSale();

        return [
            'lot_ids' => ['required', 'array', 'min:1'],
            'lot_ids.*' => ['integer', 'distinct', 'exists:lots,id'],
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
            'co_buyer_ids' => ['nullable', 'array'],
            'co_buyer_ids.*' => ['integer', 'exists:clients,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lotIds = array_filter((array) $this->input('lot_ids', []));

            if ($lotIds === []) {
                return;
            }

            $lots = Lot::query()->whereIn('id', $lotIds)->get(['id', 'development_id', 'status']);

            if ($lots->pluck('development_id')->unique()->count() > 1) {
                $validator->errors()->add('lot_ids', 'Todos os lotes da venda devem pertencer ao mesmo empreendimento.');
            }

            if ($lots->contains(fn (Lot $lot) => $lot->status !== Lot::STATUS_AVAILABLE)) {
                $validator->errors()->add('lot_ids', 'Todos os lotes selecionados precisam estar disponíveis.');
            }
        });
    }
}
