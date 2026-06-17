<?php

declare(strict_types=1);

namespace App\Actions\Whatsapp;

use App\Actions\Client\FindClientByWhatsappPhoneAction;
use App\Models\Client;
use App\Models\InstallmentInteraction;
use App\Models\Setting;
use App\Models\WhatsappConversationState;
use App\Services\WhatsappBotService;
use App\Services\WhatsappConversationStateService;
use App\Services\WhatsappService;
use App\Support\WhatsappBotMessageFooter;
use App\Support\WhatsappCommandParser;
use Illuminate\Support\Facades\Log;

class ProcessWhatsappBotMessageAction
{
    public function __construct(
        private readonly FindClientByWhatsappPhoneAction $findClient,
        private readonly WhatsappBotService $bot,
        private readonly WhatsappService $whatsapp,
        private readonly WhatsappConversationStateService $conversationState,
        private readonly WhatsappCommandParser $commandParser,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(string $from, string $body, array $payload = []): void
    {
        if (! Setting::get('whatsapp_bot_enabled', true)) {
            return;
        }

        $client = $this->findClient->execute($from);

        if ($client === null) {
            $this->recordUnknownContact($from, $body, $payload);

            return;
        }

        $replyTo = $this->replyAddress($from, $client);
        $state = $this->conversationState->findOrCreate($from, $client->id);
        $this->conversationState->touchInbound($state);

        [$command] = $this->commandParser->parse($body);

        Log::info('WhatsApp bot: processing command', [
            'client_id' => $client->id,
            'body' => $body,
            'from' => $payload['from'] ?? null,
            'command' => $command,
            'conversation_status' => $state->status,
        ]);

        if ($command === WhatsappCommandParser::COMMAND_PAUSE) {
            $this->handlePause($client, $replyTo, $body, $state, $payload);

            return;
        }

        if ($command === WhatsappCommandParser::COMMAND_RESUME) {
            $this->handleResume($client, $replyTo, $body, $state, $payload);

            return;
        }

        if (in_array($command, [
            WhatsappCommandParser::COMMAND_HUMAN,
            WhatsappCommandParser::COMMAND_SUPPORT,
        ], true)) {
            $this->handleHumanMode($client, $replyTo, $body, $state, $payload, $command);

            return;
        }

        if ($this->conversationState->shouldIgnoreInbound($state, $command)) {
            $this->bot->recordIgnoredInbound($client, $replyTo, $body, $state, $payload, $command);

            return;
        }

        try {
            $this->bot->handle($client, $replyTo, $body, $payload, $state);
        } catch (\Throwable $e) {
            Log::error('WhatsApp bot: handle failed', [
                'client_id' => $client->id,
                'body' => $body,
                'error' => $e->getMessage(),
            ]);

            $this->sendAutomaticMessage(
                $client,
                $replyTo,
                "Desculpe, ocorreu um erro ao processar sua mensagem.\n\nDigite *MENU* para ver os comandos disponíveis.",
                InstallmentInteraction::TYPE_BOT_RESPONSE,
                $state,
                ['command' => WhatsappCommandParser::COMMAND_UNKNOWN, 'error' => true],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePause(
        Client $client,
        string $phone,
        string $body,
        WhatsappConversationState $state,
        array $payload,
    ): void {
        if ($state->isBotPaused()) {
            $this->bot->recordIgnoredInbound($client, $phone, $body, $state, $payload, WhatsappCommandParser::COMMAND_PAUSE);

            return;
        }

        $state = $this->conversationState->pause($state);

        $this->bot->recordInboundCommand($client, $phone, $body, WhatsappCommandParser::COMMAND_PAUSE, $payload);

        $this->sendAutomaticMessage(
            $client,
            $phone,
            "✅ *{$client->name}*, a assistência automática foi pausada.\n\nQuando quiser retomar, digite *INICIAR*, *MENU* ou *VOLTAR*.",
            InstallmentInteraction::TYPE_BOT_PAUSE,
            $state,
            ['command' => WhatsappCommandParser::COMMAND_PAUSE],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleResume(
        Client $client,
        string $phone,
        string $body,
        WhatsappConversationState $state,
        array $payload,
    ): void {
        $state = $this->conversationState->activateBot($state);

        $this->bot->handle(
            $client,
            $phone,
            $body,
            $payload,
            $state,
            WhatsappCommandParser::COMMAND_RESUME,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleHumanMode(
        Client $client,
        string $phone,
        string $body,
        WhatsappConversationState $state,
        array $payload,
        string $command,
    ): void {
        $state = $this->conversationState->enterHumanMode($state);

        $this->bot->handle($client, $phone, $body, $payload, $state, $command);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordUnknownContact(string $from, string $body, array $payload): void
    {
        $phone = $this->conversationState->normalizePhone($from);

        Log::info('WhatsApp bot: unknown contact ignored', [
            'from' => $from,
            'phone' => $phone,
            'body' => $body,
        ]);

        if ($phone !== '') {
            $this->conversationState->findOrCreate($from, null);
        }

        InstallmentInteraction::query()->create([
            'phone' => $phone !== '' ? $phone : $from,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_UNKNOWN_CONTACT,
            'message' => $body,
            'meta' => [
                'from' => $from,
                'ignored' => true,
                'reason' => 'unknown_contact',
                'message_type' => $payload['type'] ?? null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function sendAutomaticMessage(
        Client $client,
        string $phone,
        string $message,
        string $type,
        WhatsappConversationState $state,
        array $meta = [],
    ): void {
        $this->whatsapp->sendAndRecord(
            phone: $phone,
            message: WhatsappBotMessageFooter::append($message),
            type: $type,
            clientId: $client->id,
            meta: $meta,
        );

        $this->conversationState->touchOutbound($state);
    }

    private function replyAddress(string $from, Client $client): string
    {
        $from = trim($from);

        if (str_contains($from, '@')) {
            return $from;
        }

        return (string) ($client->phone ?? $from);
    }
}
