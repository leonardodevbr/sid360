<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentStreet extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'development_id',
        'name',
        'coordinates',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return HasMany<Lot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class, 'street_id');
    }
}
