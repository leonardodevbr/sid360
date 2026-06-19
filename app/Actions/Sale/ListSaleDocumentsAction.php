<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Collection;

class ListSaleDocumentsAction
{
    /**
     * @return Collection<int, \App\Models\SaleDocument>
     */
    public function execute(Sale $sale): Collection
    {
        return $sale->documents()
            ->orderBy('type')
            ->orderBy('side')
            ->get();
    }
}
