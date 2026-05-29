<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;
use App\Models\InstallmentInteraction;
use App\Support\DocumentHelper;

class FindClientByWhatsappPhoneAction
{
    public function execute(string $from): ?Client
    {
        $from = trim($from);

        if ($from === '') {
            return null;
        }

        $raw = preg_replace('/@.+$/', '', $from) ?? $from;

        $client = Client::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(fn (Client $client): bool => DocumentHelper::phoneMatches($client->phone, $raw));

        if ($client !== null) {
            return $client;
        }

        $clientId = $this->findClientIdByWhatsappChat($from);

        if ($clientId !== null) {
            return Client::query()->find($clientId);
        }

        if ($raw === '') {
            return null;
        }

        $interaction = InstallmentInteraction::query()
            ->whereNotNull('client_id')
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->limit(300)
            ->get()
            ->first(fn (InstallmentInteraction $row): bool => DocumentHelper::phoneMatches($row->phone, $raw));

        if ($interaction?->client_id === null) {
            return null;
        }

        return Client::query()->find($interaction->client_id);
    }

    private function findClientIdByWhatsappChat(string $from): ?int
    {
        $interaction = InstallmentInteraction::query()
            ->whereNotNull('client_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('meta->from', $from)
            ->latest()
            ->value('client_id');

        return $interaction !== null ? (int) $interaction : null;
    }
}
