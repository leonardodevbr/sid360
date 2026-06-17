<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentZone extends Model
{
    /**
     * @var list<string>
     */
    public const TYPES = ['quadra', 'conjunto', 'setor', 'rua', 'outro'];

    /**
     * @var list<string>
     */
    public const LOT_GENERATION_TYPES = ['quadra', 'conjunto', 'setor'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'development_id',
        'parent_zone_id',
        'name',
        'type',
        'color',
        'coordinates',
        'order',
        'price_per_m2',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
            'price_per_m2' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Development, $this>
     */
    public function development(): BelongsTo
    {
        return $this->belongsTo(Development::class);
    }

    /**
     * @return BelongsTo<DevelopmentZone, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DevelopmentZone::class, 'parent_zone_id');
    }

    /**
     * @return HasMany<DevelopmentZone, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(DevelopmentZone::class, 'parent_zone_id')->orderBy('order');
    }

    /**
     * @return HasMany<Lot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class, 'zone_id');
    }

    public function allowsLotGeneration(): bool
    {
        return in_array($this->type, self::LOT_GENERATION_TYPES, true)
            && is_array($this->coordinates)
            && count($this->coordinates) >= 3;
    }
}
