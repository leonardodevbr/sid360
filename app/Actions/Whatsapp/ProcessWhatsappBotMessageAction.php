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
use App\Support\DocumentHelper;
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
    public function execute(string $from, string $body, array $payload = [], ?string $phoneHint = null): void
    {
        if (! Setting::get('whatsapp_bot_enabled', true)) {
            return;
        }

        $client = $this->findClient->execute($from, $phoneHint);

        // O WhatsApp pode esconder o telefone real (@lid) sem deixar
        // nenhum jeito automático de recuperá-lo (confirmado: o endpoint
        // de resolução do WPPConnect só ecoa o próprio LID quando não tem
        // mapeamento cacheado). Se o contato respondeu com CPF ou telefone
        // — por exemplo depois do pedido de identificação em
        // recordUnknownContact() — tentamos casar aqui antes de desistir.
        if ($client === null) {
            $client = $this->findClientByIdentification($body);

            if ($client !== null) {
                Log::info('WhatsApp bot: contato desconhecido identificado por CPF/telefone informado', [
                    'from' => $from,
                    'client_id' => $client->id,
                ]);
            }
        }

        if ($client === null) {
            $this->recordUnknownContact($from, $body, $payload, $phoneHint);

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
                'trace' => $e->getTraceAsString(),
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
            "✅ A assistência automática foi pausada.\n\nQuando quiser retomar, digite *INICIAR*, *MENU* ou *VOLTAR*.",
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
    private function recordUnknownContact(string $from, string $body, array $payload, ?string $phoneHint = null): void
    {
        $phone = $this->conversationState->normalizePhone($from);

        Log::info('WhatsApp bot: unknown contact ignored', [
            'from' => $from,
            'phone' => $phone,
            'phone_hint' => $phoneHint,
            'body' => $body,
        ]);

        if ($phone !== '') {
            $this->conversationState->findOrCreate($from, null);
        }

        InstallmentInteraction::query()->create([
            'phone' => $phone !== '' ? $phone : $from,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_UNKNOWN_CONTACT,
            // Trava extra: mesmo que o body já tenha sido tratado em
            // WhatsappWebhookController::resolveMessageBody(), esta é
            // exatamente a linha que quebrou em produção (SQLSTATE[22001]
            // "Data too long for column 'message'") com um áudio cujo body
            // bruto (base64) ultrapassou os 65.535 bytes da coluna TEXT.
            'message' => mb_strlen($body) > 4000 ? mb_substr($body, 0, 4000).'…' : $body,
            'meta' => [
                'from' => $from,
                'ignored' => true,
                'reason' => 'unknown_contact',
                'message_type' => $payload['type'] ?? null,
                'phone_hint' => $phoneHint,
            ],
        ]);

        $this->promptForIdentification($from);
    }

    /**
     * Tenta casar um CPF ou telefone informado em texto livre com um
     * Client cadastrado. Usado quando o contato não bateu com nenhum
     * Client automaticamente (ex.: @lid sem telefone real disponível) e
     * respondeu ao pedido de identificação feito em promptForIdentification().
     */
    private function findClientByIdentification(string $body): ?Client
    {
        $digits = preg_replace('/\D/', '', $body) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            $client = Client::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get()
                ->first(fn (Client $c): bool => DocumentHelper::phoneMatches($c->phone, $digits));

            if ($client !== null) {
                return $client;
            }
        }

        if (strlen($digits) === 11) {
            return Client::query()->where('cpf', $digits)->first();
        }

        return null;
    }

    /**
     * Antes, contato desconhecido era só ignorado em silêncio — e era
     * exatamente isso que parecia "o bot não responde" pros casos de @lid
     * (sem telefone recuperável) ou número não cadastrado. Pedimos CPF ou
     * telefone uma vez por janela de 24h pra esse "from", pra dar uma
     * chance de identificação manual sem floodar o contato a cada
     * mensagem nova.
     */
    private function promptForIdentification(string $from): void
    {
        $alreadyPrompted = InstallmentInteraction::query()
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->where('type', InstallmentInteraction::TYPE_BOT_UNKNOWN_CONTACT)
            ->where('meta->from', $from)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if ($alreadyPrompted) {
            return;
        }

        $this->whatsapp->sendAndRecord(
            phone: $from,
            message: "Olá! Não conseguimos localizar automaticamente seu contrato a partir deste número.\n\nPara te ajudar, responda com seu *CPF* ou o *telefone* cadastrado.",
            type: InstallmentInteraction::TYPE_BOT_UNKNOWN_CONTACT,
            meta: ['from' => $from],
        );
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
            message: $message,
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
