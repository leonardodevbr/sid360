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
        $raw = preg_replace('/@.+$/', '', $from) ?? $from;

        if ($raw === '') {
            return null;
        }

        $client = Client::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(fn (Client $client): bool => DocumentHelper::phoneMatches($client->phone, $raw));

        if ($client !== null) {
            return $client;
        }

        $interaction = InstallmentInteraction::query()
            ->whereNotNull('client_id')
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->latest()
            ->limit(200)
            ->get()
            ->first(fn (InstallmentInteraction $row): bool => DocumentHelper::phoneMatches($row->phone, $raw));

        if ($interaction?->client_id === null) {
            return null;
        }

        return Client::query()->find($interaction->client_id);
    }
}
