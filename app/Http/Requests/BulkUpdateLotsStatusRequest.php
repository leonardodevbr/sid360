<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateLotsStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(Lot::STATUSES)],
        ];
    }
}
