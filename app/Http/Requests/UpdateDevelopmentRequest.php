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
            'coordinates' => ['nullable', 'array'],
            'lot_number_pattern' => ['nullable', 'string', 'max:100'],
            'map_center' => ['nullable', 'array'],
            'map_zoom' => ['nullable', 'integer'],
            'map_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
