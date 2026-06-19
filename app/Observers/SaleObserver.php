<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SendWelcomeWhatsappJob;
use App\Models\Installment;
use App\Models\Lot;
use App\Models\Sale;
use Carbon\Carbon;

class SaleObserver
{
    public function created(Sale $sale): void
    {
        Lot::query()->where('id', $sale->lot_id)->update(['status' => Lot::STATUS_SOLD]);

        $sale->buyers()->syncWithoutDetaching([
            $sale->client_id => ['role' => 'buyer', 'order' => 0],
        ]);

        SendWelcomeWhatsappJob::dispatch($sale)->delay(now()->addSeconds(30));

        if ((int) $sale->down_payment > 0) {
            Installment::query()->create([
                'sale_id' => $sale->id,
                'type' => Installment::TYPE_DOWN_PAYMENT,
                'number' => 0,
                'due_date' => $sale->sale_date,
                'value' => $sale->down_payment,
                'status' => Installment::STATUS_PENDING,
            ]);
        }

        if ($sale->installments_count < 1) {
            return;
        }

        $dueDate = Carbon::parse($sale->first_due_date);

        for ($i = 1; $i <= $sale->installments_count; $i++) {
            Installment::query()->create([
                'sale_id' => $sale->id,
                'type' => Installment::TYPE_FINANCING,
                'number' => $i,
                'due_date' => $dueDate->copy(),
                'value' => $sale->installment_value,
                'status' => Installment::STATUS_PENDING,
            ]);
            $dueDate->addMonth();
        }
    }

    /**
     * Usa `deleting` (não `deleted`): a FK de `sale_lots.sale_id` é
     * cascadeOnDelete, então ao chegar em `deleted` o pivot já teria sido
     * apagado e não saberíamos mais quais lotes liberar.
     */
    public function deleting(Sale $sale): void
    {
        $lotIds = $sale->lots()->pluck('lots.id');
        if ($lotIds->isEmpty()) {
            $lotIds = collect([$sale->lot_id])->filter();
        }

        Lot::query()->whereIn('id', $lotIds)->update(['status' => Lot::STATUS_AVAILABLE]);
    }
}
