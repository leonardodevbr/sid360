<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ClientDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSaleDocumentRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'type' => ['required', 'string', Rule::in(ClientDocument::TYPES)],
            'side' => ['nullable', 'string', Rule::in(ClientDocument::SIDES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Selecione o arquivo do documento.',
            'file.mimes' => 'O arquivo deve ser PDF ou imagem (JPG, PNG ou WebP).',
            'file.max' => 'O arquivo não pode ter mais de 10 MB.',
            'type.required' => 'Selecione o tipo do documento.',
            'type.in' => 'Tipo de documento inválido.',
            'side.in' => 'Lado do documento inválido.',
        ];
    }
}
