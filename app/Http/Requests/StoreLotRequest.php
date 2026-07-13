<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lots.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $developmentId = (int) $this->input('development_id');

        return [
            'development_id' => ['required', 'integer', 'exists:developments,id'],
            'zone_id' => ['nullable', 'integer', 'exists:development_zones,id'],
            'street_id' => ['nullable', 'integer', 'exists:development_streets,id'],
            'number' => [
                'required',
                'string',
                'max:50',
                Lot::uniqueNumberInDevelopmentRule($developmentId, $this->input('block')),
            ],
            'block' => ['nullable', 'string', 'max:50'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'area_computed' => ['nullable', 'numeric', 'min:0'],
            'size_label' => ['nullable', 'string', 'max:255'],
            'faces' => ['nullable', 'array'],
            'faces.*.name' => ['required_with:faces', 'string', 'max:100'],
            'faces.*.meters' => ['required_with:faces', 'numeric', 'min:0.01'],
            'contract_measures_text' => ['nullable', 'string', 'max:5000'],
            'coordinates' => ['nullable', 'array'],
            'total_value' => ['nullable', 'integer', 'min:0'],
            'down_payment_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(Lot::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.unique' => 'Já existe um lote com este número nesta quadra.',
        ];
    }
}
