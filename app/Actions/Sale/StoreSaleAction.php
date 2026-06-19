<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Lot;
use App\Models\Sale;

class StoreSaleAction
{
    /**
     * Cria a venda com o(s) lote(s) informados em `lot_ids`. O primeiro lote
     * da lista é gravado em `sales.lot_id` (lote "primário", mantido por
     * compatibilidade com qualquer consumidor que ainda leia apenas esse
     * campo); todos os lotes informados são vinculados via pivot `sale_lots`.
     *
     * @param array<string, mixed> $data
     */
    public function execute(array $data): Sale
    {
        $lotIds = array_values(array_unique(array_map('intval', $data['lot_ids'] ?? [])));
        unset($data['lot_ids']);

        $data['lot_id'] = $lotIds[0] ?? null;

        $sale = Sale::query()->create($data);

        $pivot = [];
        foreach ($lotIds as $order => $lotId) {
            $pivot[$lotId] = ['order' => $order];
        }
        $sale->lots()->sync($pivot);

        // O lote primário já é marcado como Vendido pelo SaleObserver::created().
        // Os demais lotes (venda com múltiplos lotes) são marcados aqui.
        $extraLotIds = array_slice($lotIds, 1);
        if ($extraLotIds !== []) {
            Lot::query()->whereIn('id', $extraLotIds)->update(['status' => Lot::STATUS_SOLD]);
        }

        return $sale;
    }
}
