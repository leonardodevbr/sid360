<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;

class DeleteSaleAction
{
    public function execute(Sale $sale): void
    {
        $sale->delete();
    }
}
