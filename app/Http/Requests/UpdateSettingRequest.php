<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['nullable'],
            'settings.*.group' => ['nullable', 'string', 'max:50'],
            'settings.*.type' => ['nullable', 'string', 'in:string,boolean,integer,json'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.*.value.url' => 'Informe uma URL válida para o servidor WPPConnect.',
            'settings.*.value.min' => 'O valor informado é inválido.',
            'settings.*.value.max' => 'O valor informado é inválido.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $settings = $this->input('settings', []);

            if (! is_array($settings)) {
                return;
            }

            foreach ($settings as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;

                if ($key === 'wppconnect_base_url' && is_string($value) && trim($value) !== '') {
                    if (! filter_var($value, FILTER_VALIDATE_URL)) {
                        $validator->errors()->add("settings.{$index}.value", 'Informe uma URL válida para o servidor WPPConnect.');
                    }
                }

                if (in_array($key, ['wppconnect_timeout', 'wppconnect_media_timeout'], true) && $value !== null && $value !== '') {
                    if (! is_numeric($value) || (int) $value < 1 || (int) $value > 600) {
                        $validator->errors()->add("settings.{$index}.value", 'O timeout deve estar entre 1 e 600 segundos.');
                    }
                }
            }
        });
    }
}
