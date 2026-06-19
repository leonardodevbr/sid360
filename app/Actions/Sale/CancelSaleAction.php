<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Installment;
use App\Models\Lot;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelSaleAction
{
    /**
     * Cancela a venda: marca a venda como cancelada (com motivo), cancela as
     * parcelas ainda pendentes (parcelas pagas são preservadas — nunca
     * apagamos histórico de pagamento) e devolve o(s) lote(s) para
     * Disponível. Tudo dentro de uma transação para evitar estado parcial.
     */
    public function execute(Sale $sale, string $reason): Sale
    {
        if ($sale->status === Sale::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Esta venda já está cancelada.');
        }

        DB::transaction(function () use ($sale, $reason): void {
            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $sale->installments()
                ->where('status', Installment::STATUS_PENDING)
                ->update(['status' => Installment::STATUS_CANCELLED]);

            $lotIds = $sale->lots()->pluck('lots.id');
            if ($lotIds->isEmpty()) {
                $lotIds = collect([$sale->lot_id])->filter();
            }

            Lot::query()->whereIn('id', $lotIds)->update(['status' => Lot::STATUS_AVAILABLE]);
        });

        return $sale->refresh();
    }
}
