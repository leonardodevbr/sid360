<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;

class UpdateSaleAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(Sale $sale, array $data): Sale
    {
        $sale->update($data);

        return $sale->fresh();
    }
}
