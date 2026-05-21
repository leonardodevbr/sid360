<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    public const TYPE_DOWN_PAYMENT = 'down_payment';

    public const TYPE_FINANCING = 'financing';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'type',
        'number',
        'due_date',
        'value',
        'paid_at',
        'status',
        'whatsapp_reminder_sent_at',
        'whatsapp_overdue_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'date',
            'value' => 'integer',
            'whatsapp_reminder_sent_at' => 'datetime',
            'whatsapp_overdue_sent_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Installment>  $query
     * @return Builder<Installment>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<InstallmentInteraction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(InstallmentInteraction::class)->latest();
    }

    public function lastWhatsappNotificationAt(): ?Carbon
    {
        /** @var Carbon|null $latest */
        $latest = collect([
            $this->whatsapp_reminder_sent_at,
            $this->whatsapp_overdue_sent_at,
        ])->filter()->sort()->last();

        return $latest instanceof Carbon ? $latest : null;
    }

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_PAID) {
            return self::STATUS_PAID;
        }

        if (
            $this->status === self::STATUS_PENDING
            && $this->due_date !== null
            && $this->due_date->lt(now()->startOfDay())
        ) {
            return self::STATUS_OVERDUE;
        }

        return $this->status;
    }

    public function isOverdue(): bool
    {
        return $this->displayStatus() === self::STATUS_OVERDUE;
    }
}
