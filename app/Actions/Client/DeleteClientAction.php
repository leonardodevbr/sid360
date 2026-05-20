<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;

class DeleteClientAction
{
    public function execute(Client $client): void
    {
        $client->delete();
    }
}
