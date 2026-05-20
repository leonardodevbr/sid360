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
    ];

    /**
     * @return HasMany<Lot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
