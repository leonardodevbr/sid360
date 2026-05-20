<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;

class StoreClientAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): Client
    {
        return Client::query()->create($data);
    }
}
