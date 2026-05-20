<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cpf',
        'rg',
        'rg_issuer',
        'phone',
        'whatsapp_status',
        'email',
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
}
