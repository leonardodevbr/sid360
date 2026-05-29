<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;
use App\Support\DocumentHelper;

class FindClientByWhatsappPhoneAction
{
    public function execute(string $from): ?Client
    {
        $raw = preg_replace('/@.+$/', '', $from) ?? $from;

        if ($raw === '') {
            return null;
        }

        return Client::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(fn (Client $client): bool => DocumentHelper::phoneMatches($client->phone, $raw));
    }
}
