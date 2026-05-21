<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lot_id',
        'client_id',
        'sale_date',
        'total_value',
        'cash_value',
        'discount_amount',
        'discount_percent',
        'down_payment',
        'financed_value',
        'installments_count',
        'installment_value',
        'first_due_date',
        'payment_day',
        'status',
        'notes',
        'signed_contract_path',
        'signed_contract_original_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'first_due_date' => 'date',
            'total_value' => 'integer',
            'cash_value' => 'integer',
            'discount_amount' => 'integer',
            'discount_percent' => 'decimal:2',
            'down_payment' => 'integer',
            'financed_value' => 'integer',
            'installment_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function buyers(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'sale_clients')
            ->withPivot('role', 'order')
            ->orderByPivot('order');
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)
            ->orderBy('type')
            ->orderBy('number');
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function downPaymentInstallments(): HasMany
    {
        return $this->hasMany(Installment::class)
            ->where('type', Installment::TYPE_DOWN_PAYMENT)
            ->orderBy('number');
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function financingInstallments(): HasMany
    {
        return $this->hasMany(Installment::class)
            ->where('type', Installment::TYPE_FINANCING)
            ->orderBy('number');
    }
}
