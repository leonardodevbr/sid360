<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lots.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'development_id' => ['sometimes', 'integer', 'exists:developments,id'],
            'zone_id' => ['nullable', 'integer', 'exists:development_zones,id'],
            'street_id' => ['nullable', 'integer', 'exists:development_streets,id'],
            'number' => ['sometimes', 'required', 'string', 'max:50'],
            'block' => ['nullable', 'string', 'max:50'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'area_computed' => ['nullable', 'numeric', 'min:0'],
            'size_label' => ['nullable', 'string', 'max:50'],
            'coordinates' => ['nullable', 'array'],
            'total_value' => ['nullable', 'integer', 'min:0'],
            'down_payment_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(Lot::STATUSES)],
        ];
    }
}
