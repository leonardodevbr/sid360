<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;

class StoreSaleAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): Sale
    {
        return Sale::query()->create($data);
    }
}
