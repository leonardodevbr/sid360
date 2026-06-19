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

    public const PAYMENT_METHOD_DINHEIRO = 'dinheiro';

    public const PAYMENT_METHOD_PIX = 'pix';

    public const PAYMENT_METHOD_CARTAO = 'cartao';

    public const PAYMENT_METHOD_TRANSFERENCIA = 'transferencia';

    public const PAYMENT_METHOD_PERMUTA = 'permuta';

    public const PAYMENT_METHOD_OUTRO = 'outro';

    /**
     * @var list<string>
     */
    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_DINHEIRO,
        self::PAYMENT_METHOD_PIX,
        self::PAYMENT_METHOD_CARTAO,
        self::PAYMENT_METHOD_TRANSFERENCIA,
        self::PAYMENT_METHOD_PERMUTA,
        self::PAYMENT_METHOD_OUTRO,
    ];

    /**
     * Métodos que exigem uma descrição livre (ex.: "Veículo Fiat Uno 2018,
     * placa ABC1234") por não se tratarem de pagamento direto em dinheiro/PIX
     * processado pelo sistema.
     *
     * @var list<string>
     */
    public const PAYMENT_METHODS_REQUIRING_DESCRIPTION = [
        self::PAYMENT_METHOD_CARTAO,
        self::PAYMENT_METHOD_TRANSFERENCIA,
        self::PAYMENT_METHOD_PERMUTA,
        self::PAYMENT_METHOD_OUTRO,
    ];

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
        'payment_method',
        'payment_method_description',
        'whatsapp_reminder_sent_at',
        'whatsapp_overdue_sent_at',
        'efi_charge_id',
        'efi_txid',
        'efi_barcode',
        'efi_pdf_url',
        'efi_pix_copia_cola',
        'efi_pix_qrcode',
        'efi_payment_type',
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

    public static function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            self::PAYMENT_METHOD_DINHEIRO => 'Dinheiro',
            self::PAYMENT_METHOD_PIX => 'PIX',
            self::PAYMENT_METHOD_CARTAO => 'Cartão',
            self::PAYMENT_METHOD_TRANSFERENCIA => 'Transferência',
            self::PAYMENT_METHOD_PERMUTA => 'Permuta/Bem',
            self::PAYMENT_METHOD_OUTRO => 'Outro',
            default => '',
        };
    }
}
