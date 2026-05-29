<?php

declare(strict_types=1);

namespace App\Actions\Whatsapp;

use App\Actions\Client\FindClientByWhatsappPhoneAction;
use App\Models\Setting;
use App\Services\WhatsappBotService;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

class ProcessWhatsappBotMessageAction
{
    public function __construct(
        private readonly FindClientByWhatsappPhoneAction $findClient,
        private readonly WhatsappBotService $bot,
        private readonly WhatsappService $whatsapp,
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
            Log::info('WhatsApp bot: client not found for phone', ['from' => $from, 'body' => $body]);

            $this->whatsapp->send(
                $from,
                "Olá! Não encontramos seu cadastro na Sid360.\n\nAcesse sid360.com.br/pagamentos ou fale com a corretora: (74) 9 8823-0151",
            );

            return;
        }

        Log::info('WhatsApp bot: processing command', [
            'client_id' => $client->id,
            'body' => $body,
        ]);

        $phone = (string) ($client->phone ?? $from);

        $this->bot->handle($client, $phone, $body, $payload);
    }
}
