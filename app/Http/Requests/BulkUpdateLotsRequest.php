<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateLotsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:lots,id'],
            'zone_id' => ['sometimes', 'nullable', 'integer', 'exists:development_zones,id'],
            'block' => ['sometimes', 'nullable', 'string', 'max:50'],
            'area' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'size_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'faces' => ['sometimes', 'nullable', 'array'],
            'faces.*.name' => ['required_with:faces', 'string', 'max:100'],
            'faces.*.meters' => ['required_with:faces', 'numeric', 'min:0.01'],
            'total_value' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(Lot::STATUSES)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $updateKeys = ['zone_id', 'block', 'area', 'size_label', 'faces', 'total_value', 'status'];
            $hasField = false;

            foreach ($updateKeys as $key) {
                if ($this->exists($key)) {
                    $hasField = true;
                    break;
                }
            }

            if (! $hasField) {
                $validator->errors()->add('fields', 'Informe ao menos um campo para atualizar.');
            }
        });
    }
}
