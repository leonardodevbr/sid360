<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WhatsappConversationState;
use App\Support\WhatsappCommandParser;
use Carbon\Carbon;

class WhatsappConversationStateService
{
    public const HUMAN_MODE_HOURS = 24;

    public function __construct(
        private readonly WhatsappCommandParser $commandParser,
    ) {}

    public function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D/', '', preg_replace('/@.+$/', '', $phone) ?? $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return $digits;
        }

        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            return '55'.$digits;
        }

        return $digits;
    }

    public function findOrCreate(string $phone, ?int $clientId = null): WhatsappConversationState
    {
        $normalizedPhone = $this->normalizePhone($phone);

        $state = WhatsappConversationState::query()->firstOrCreate(
            ['phone' => $normalizedPhone],
            [
                'client_id' => $clientId,
                'status' => WhatsappConversationState::STATUS_BOT_ACTIVE,
            ],
        );

        if ($clientId !== null && $state->client_id === null) {
            $state->client_id = $clientId;
            $state->save();
        }

        return $this->refreshExpiredHumanMode($state);
    }

    public function touchInbound(WhatsappConversationState $state): void
    {
        $state->last_inbound_at = now();
        $state->save();
    }

    public function touchOutbound(WhatsappConversationState $state): void
    {
        $state->last_outbound_at = now();
        $state->save();
    }

    public function pause(WhatsappConversationState $state): WhatsappConversationState
    {
        $state->status = WhatsappConversationState::STATUS_BOT_PAUSED;
        $state->paused_at = now();
        $state->human_until = null;
        $state->save();

        return $state->fresh() ?? $state;
    }

    public function activateBot(WhatsappConversationState $state): WhatsappConversationState
    {
        $state->status = WhatsappConversationState::STATUS_BOT_ACTIVE;
        $state->paused_at = null;
        $state->human_until = null;
        $state->save();

        return $state->fresh() ?? $state;
    }

    public function enterHumanMode(
        WhatsappConversationState $state,
        ?int $hours = null,
    ): WhatsappConversationState {
        $hours = $hours ?? self::HUMAN_MODE_HOURS;

        $state->status = WhatsappConversationState::STATUS_HUMAN;
        $state->human_until = Carbon::now()->addHours($hours);
        $state->paused_at = null;
        $state->save();

        return $state->fresh() ?? $state;
    }

    public function isResumeCommand(string $command): bool
    {
        return $command === WhatsappCommandParser::COMMAND_RESUME;
    }

    public function shouldIgnoreInbound(WhatsappConversationState $state, string $command): bool
    {
        if ($this->isResumeCommand($command)) {
            return false;
        }

        $state = $this->refreshExpiredHumanMode($state);

        if ($state->isBotPaused()) {
            return true;
        }

        if ($state->isHumanModeActive()) {
            return true;
        }

        return false;
    }

    public function refreshExpiredHumanMode(WhatsappConversationState $state): WhatsappConversationState
    {
        if ($state->status !== WhatsappConversationState::STATUS_HUMAN) {
            return $state;
        }

        if ($state->human_until !== null && $state->human_until->isPast()) {
            return $this->activateBot($state);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function appendMetadata(WhatsappConversationState $state, array $meta): void
    {
        $current = is_array($state->metadata) ? $state->metadata : [];
        $state->metadata = array_merge($current, $meta);
        $state->save();
    }
}
