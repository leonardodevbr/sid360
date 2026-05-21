<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
