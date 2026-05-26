<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Development extends Model
{
    use HasMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'location',
        'status',
        'is_featured',
        'down_payment_percent',
        'coordinates',
        'lot_number_pattern',
        'map_center',
        'map_zoom',
        'map_color',
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
            'is_featured' => 'boolean',
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

    /**
     * @return HasMany<DevelopmentStreet, $this>
     */
    public function streets(): HasMany
    {
        return $this->hasMany(DevelopmentStreet::class)->orderBy('order');
    }
}
