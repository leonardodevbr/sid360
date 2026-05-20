<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;

class UpdateClientAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->fresh();
    }
}
