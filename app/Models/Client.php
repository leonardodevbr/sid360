<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    public const MARITAL_SINGLE = 'single';

    public const MARITAL_MARRIED = 'married';

    public const MARITAL_DIVORCED = 'divorced';

    public const MARITAL_WIDOWED = 'widowed';

    public const MARITAL_SEPARATED = 'separated';

    public const MARITAL_STABLE_UNION = 'stable_union';

    /**
     * @var list<string>
     */
    public const MARITAL_STATUSES = [
        self::MARITAL_SINGLE,
        self::MARITAL_MARRIED,
        self::MARITAL_DIVORCED,
        self::MARITAL_WIDOWED,
        self::MARITAL_SEPARATED,
        self::MARITAL_STABLE_UNION,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cpf',
        'rg',
        'rg_issuer',
        'profession',
        'marital_status',
        'phone',
        'whatsapp_status',
        'email',
        'zip_code',
        'address',
        'address_number',
        'neighborhood',
        'city',
        'state',
        'notes',
    ];

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->address_number ? 'nº '.$this->address_number : null,
            $this->neighborhood,
        ]);

        return implode(', ', $parts);
    }

    public static function maritalStatusLabel(?string $status): string
    {
        return match ($status) {
            self::MARITAL_SINGLE => 'Solteiro(a)',
            self::MARITAL_MARRIED => 'Casado(a)',
            self::MARITAL_DIVORCED => 'Divorciado(a)',
            self::MARITAL_WIDOWED => 'Viúvo(a)',
            self::MARITAL_SEPARATED => 'Separado(a)',
            self::MARITAL_STABLE_UNION => 'União estável',
            default => '',
        };
    }
}
