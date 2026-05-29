<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentInteraction extends Model
{
    public const TYPE_REMINDER = 'reminder';

    public const TYPE_OVERDUE = 'overdue';

    public const TYPE_WELCOME = 'welcome';

    public const TYPE_BOLETO_LINK = 'boleto_link';

    public const TYPE_BOLETO = 'boleto';

    public const TYPE_PIX = 'pix';

    public const TYPE_NEGOTIATE_FORWARD = 'negotiate_forward';

    public const TYPE_REPLY_ACKNOWLEDGE = 'reply_acknowledge';

    public const TYPE_REPLY_BOLETO = 'reply_boleto';

    public const TYPE_REPLY_NEGOTIATE = 'reply_negotiate';

    public const TYPE_REPLY_UNKNOWN = 'reply_unknown';

    public const TYPE_BOT_COMMAND = 'bot_command';

    public const TYPE_BOT_RESPONSE = 'bot_response';

    public const TYPE_BOT_PAYMENT = 'bot_payment';

    public const TYPE_BOT_CONTRACT = 'bot_contract';

    public const TYPE_BOT_SUPPORT_NOTIFY = 'bot_support_notify';

    public const DIR_OUTBOUND = 'outbound';

    public const DIR_INBOUND = 'inbound';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'installment_id',
        'sale_id',
        'client_id',
        'phone',
        'direction',
        'type',
        'message',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'installment_id' => 'integer',
            'sale_id' => 'integer',
            'client_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Installment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
