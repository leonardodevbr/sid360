<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    use HasMedia;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SOLD = 'sold';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_RESERVED,
        self::STATUS_SOLD,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'development_id',
        'zone_id',
        'street_id',
        'number',
        'block',
        'area',
        'area_computed',
        'size_label',
        'total_value',
        'down_payment_percent',
        'status',
        'coordinates',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'area_computed' => 'decimal:2',
            'total_value' => 'integer',
            'down_payment_percent' => 'decimal:2',
            'coordinates' => 'array',
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
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DevelopmentZone::class, 'zone_id');
    }

    /**
     * @return BelongsTo<DevelopmentStreet, $this>
     */
    public function street(): BelongsTo
    {
        return $this->belongsTo(DevelopmentStreet::class, 'street_id');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function effectiveDownPaymentPercent(): float
    {
        if ($this->down_payment_percent !== null) {
            return (float) $this->down_payment_percent;
        }

        $this->loadMissing('development');

        return (float) ($this->development?->down_payment_percent ?? 20);
    }

    public function fullAddress(): string
    {
        $this->loadMissing(['street', 'zone.parent']);

        $parts = [];

        if ($this->street?->name) {
            $parts[] = $this->street->name;
        }

        if ($this->zone?->parent?->name) {
            $parts[] = $this->zone->parent->name;
        }

        if ($this->zone?->name) {
            $parts[] = $this->zone->name;
        } elseif ($this->block) {
            $parts[] = 'Quadra ' . $this->block;
        }

        $parts[] = 'Lote ' . $this->number;

        return implode(', ', $parts);
    }
}
