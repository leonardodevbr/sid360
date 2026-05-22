<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Development extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'location',
        'status',
        'down_payment_percent',
        'coordinates',
        'lot_number_pattern',
        'map_center',
        'map_zoom',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'down_payment_percent' => 'decimal:2',
            'coordinates' => 'array',
            'map_center' => 'array',
        ];
    }

    /**
     * @return HasMany<Lot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * @return HasMany<DevelopmentZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(DevelopmentZone::class)->orderBy('order');
    }
}
