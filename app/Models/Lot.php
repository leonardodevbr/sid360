<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Development, $this>
     */
    public function development(): BelongsTo
    {
        return $this->belongsTo(Development::class);
    }
}
