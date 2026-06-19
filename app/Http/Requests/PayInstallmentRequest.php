<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Installment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayInstallmentRequest extends FormRequest
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
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', Rule::in(Installment::PAYMENT_METHODS)],
            'payment_method_description' => [
                Rule::requiredIf(fn () => in_array(
                    $this->input('payment_method'),
                    Installment::PAYMENT_METHODS_REQUIRING_DESCRIPTION,
                    true,
                )),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Selecione o meio de pagamento.',
            'payment_method.in' => 'Meio de pagamento inválido.',
            'payment_method_description.required' => 'Descreva o pagamento (ex.: bem recebido em permuta).',
        ];
    }
}
