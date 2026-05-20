<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * Chaves cujo valor não deve ser exposto na API (mostrar como mascarado no frontend).
     *
     * @var list<string>
     */
    public const MASKED_KEYS = [];

    /**
     * Recupera valor de configuração pelo banco (cache via Settings). Uso em runtime.
     *
     * @param mixed $default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    /**
     * @return mixed
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'integer' => (int) $this->value,
            'json' => $this->value !== null ? json_decode($this->value, true, 512, JSON_THROW_ON_ERROR) : null,
            default => $this->value,
        };
    }

    /**
     * @param mixed $value
     */
    public function setTypedValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => $value !== null ? json_encode($value, JSON_THROW_ON_ERROR) : null,
            default => $value !== null ? (string) $value : null,
        };
    }
}

