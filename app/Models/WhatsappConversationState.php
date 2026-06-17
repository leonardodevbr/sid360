<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappConversationState extends Model
{
    public const STATUS_BOT_ACTIVE = 'bot_active';

    public const STATUS_BOT_PAUSED = 'bot_paused';

    public const STATUS_HUMAN = 'human';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'client_id',
        'status',
        'paused_at',
        'human_until',
        'last_inbound_at',
        'last_outbound_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paused_at' => 'datetime',
            'human_until' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isBotPaused(): bool
    {
        return $this->status === self::STATUS_BOT_PAUSED;
    }

    public function isHumanModeActive(): bool
    {
        return $this->status === self::STATUS_HUMAN
            && $this->human_until !== null
            && $this->human_until->isFuture();
    }
}
