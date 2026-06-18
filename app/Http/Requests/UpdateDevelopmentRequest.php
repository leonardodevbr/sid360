<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDevelopmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('developments.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'under_construction'])],
            'down_payment_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'base_price_per_m2' => ['nullable', 'integer', 'min:0'],
            'coordinates' => ['nullable', 'array'],
            'lot_number_pattern' => ['nullable', 'string', 'max:100'],
            'map_center' => ['nullable', 'array'],
            'map_zoom' => ['nullable', 'integer'],
            'map_bearing' => ['nullable', 'numeric'],
            'map_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_featured' => ['nullable', 'boolean'],
            'seller_name' => ['nullable', 'string', 'max:255'],
            'seller_cpf' => ['nullable', 'string', 'max:20'],
            'seller_rg' => ['nullable', 'string', 'max:20'],
            'seller_rg_issuer' => ['nullable', 'string', 'max:20'],
            'seller_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
