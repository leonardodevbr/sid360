<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportTechnicalMapPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'svg' => ['required', 'string', 'max:5000000'],
            'paper_size' => ['nullable', 'string', 'in:A4,A3,A2,A1,a4,a3,a2,a1'],
            'orientation' => ['nullable', 'string', 'in:portrait,landscape'],
        ];
    }
}
