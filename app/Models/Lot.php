<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
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
        'number',
        'block',
        'area',
        'total_value',
        'down_payment_percent',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'total_value' => 'integer',
            'down_payment_percent' => 'decimal:2',
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
}
