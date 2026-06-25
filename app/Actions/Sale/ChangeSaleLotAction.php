<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Installment;
use App\Models\Lot;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeSaleLotAction
{
    /**
     * Corrige o(s) lote(s) de uma venda já registrada (ex.: o corretor
     * escolheu o lote errado). Libera o(s) lote(s) antigo(s) que saem da
     * venda, marca o(s) novo(s) como Vendido, atualiza `sales.lot_id` +
     * pivot `sale_lots`, e recalcula o valor total e as parcelas — mas
     * SEM tocar em nada que já foi pago: parcelas com status `paid`
     * mantêm valor e data originais, sempre. Só a parte ainda pendente é
     * redistribuída proporcionalmente para refletir o preço do novo lote.
     *
     * Tudo dentro de uma transação, no mesmo padrão de CancelSaleAction.
     *
     * @param  list<int>  $lotIds  Novos lotes da venda (1º = lote principal)
     */
    public function execute(Sale $sale, array $lotIds): Sale
    {
        $newLotIds = array_values(array_unique(array_map('intval', $lotIds)));

        if ($newLotIds === []) {
            throw new InvalidArgumentException('Selecione ao menos um lote.');
        }

        if ($sale->status === Sale::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Não é possível trocar o lote de uma venda cancelada.');
        }

        $newLots = Lot::query()->whereIn('id', $newLotIds)->get()->keyBy('id');

        if ($newLots->count() !== count($newLotIds)) {
            throw new InvalidArgumentException('Um ou mais lotes selecionados não foram encontrados.');
        }

        $oldLotIds = $sale->lots()->pluck('lots.id')->all();
        if ($oldLotIds === []) {
            $oldLotIds = array_values(array_filter([$sale->lot_id]));
        }

        // Lotes que já eram desta venda podem continuar mesmo "ocupados"
        // (eles já estão Vendido por ela mesma). Os demais precisam estar
        // disponíveis — não dá pra roubar lote de outra venda.
        foreach ($newLotIds as $lotId) {
            if (in_array($lotId, $oldLotIds, true)) {
                continue;
            }

            $lot = $newLots->get($lotId);

            if ($lot->status !== Lot::STATUS_AVAILABLE) {
                throw new InvalidArgumentException(
                    "O lote {$this->lotLabel($lot)} não está disponível para ser vinculado a esta venda."
                );
            }
        }

        foreach ($newLots as $lot) {
            if ($lot->total_value === null) {
                throw new InvalidArgumentException(
                    "O lote {$this->lotLabel($lot)} não possui valor cadastrado, impossível recalcular a venda."
                );
            }
        }

        DB::transaction(function () use ($sale, $newLotIds, $newLots, $oldLotIds): void {
            $releasedIds = array_diff($oldLotIds, $newLotIds);
            if ($releasedIds !== []) {
                Lot::query()->whereIn('id', $releasedIds)->update(['status' => Lot::STATUS_AVAILABLE]);
            }

            $addedIds = array_diff($newLotIds, $oldLotIds);
            if ($addedIds !== []) {
                Lot::query()->whereIn('id', $addedIds)->update(['status' => Lot::STATUS_SOLD]);
            }

            $pivot = [];
            foreach ($newLotIds as $order => $lotId) {
                $pivot[$lotId] = ['order' => $order];
            }
            $sale->lots()->sync($pivot);
            $sale->lot_id = $newLotIds[0];

            $this->recalculateFinancials($sale, $newLots);

            $sale->save();
        });

        return $sale->refresh()->load(['lot.development', 'lots.development', 'installments']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Lot>  $newLots
     */
    private function recalculateFinancials(Sale $sale, $newLots): void
    {
        $newTotal = (int) $newLots->sum(fn (Lot $lot) => (int) $lot->total_value);
        $paidTotal = (int) $sale->installments()->where('status', Installment::STATUS_PAID)->sum('value');

        if ($newTotal < $paidTotal) {
            throw new InvalidArgumentException(
                'O valor do novo lote (R$ '.number_format($newTotal / 100, 2, ',', '.').
                ') é menor do que o total já pago nesta venda (R$ '.number_format($paidTotal / 100, 2, ',', '.').
                '). Ajuste os valores manualmente antes de trocar o lote.'
            );
        }

        $pendingInstallments = $sale->installments()
            ->where('status', Installment::STATUS_PENDING)
            ->orderBy('type')
            ->orderBy('number')
            ->get();

        $pendingNewSum = $newTotal - $paidTotal;

        if ($pendingInstallments->isEmpty()) {
            $sale->total_value = $newTotal;

            $isCashSale = (int) $sale->down_payment === 0 && (int) $sale->installments_count < 1;

            if ($isCashSale) {
                $discount = min((int) $sale->discount_amount, $newTotal);
                $sale->discount_amount = $discount;
                $sale->cash_value = max($newTotal - $discount, 0);

                if ($sale->discount_percent !== null && $newTotal > 0) {
                    $sale->discount_percent = round(($discount / $newTotal) * 100, 2);
                }

                return;
            }

            if ($pendingNewSum !== 0) {
                throw new InvalidArgumentException(
                    'Todas as parcelas desta venda já estão pagas — não é possível recalcular '.
                    'automaticamente a diferença de valor do novo lote. Ajuste manualmente, se necessário.'
                );
            }

            return;
        }

        $pendingOldSum = (int) $pendingInstallments->sum('value');
        $count = $pendingInstallments->count();
        $running = 0;

        foreach ($pendingInstallments as $index => $installment) {
            $isLast = $index === $count - 1;

            if ($isLast) {
                $newValue = $pendingNewSum - $running;
            } elseif ($pendingOldSum > 0) {
                $newValue = (int) round(((int) $installment->value) * $pendingNewSum / $pendingOldSum);
            } else {
                $newValue = (int) round($pendingNewSum / $count);
            }

            $newValue = max($newValue, 0);
            $running += $newValue;

            $installment->update(['value' => $newValue]);
        }

        $sale->total_value = $newTotal;
        $sale->down_payment = (int) $sale->installments()->where('type', Installment::TYPE_DOWN_PAYMENT)->sum('value');
        $sale->financed_value = (int) $sale->installments()->where('type', Installment::TYPE_FINANCING)->sum('value');

        $financingCount = $sale->installments()->where('type', Installment::TYPE_FINANCING)->count();
        $sale->installment_value = $financingCount > 0
            ? (int) round($sale->financed_value / $financingCount)
            : 0;
    }

    private function lotLabel(Lot $lot): string
    {
        $label = 'Lote '.$lot->number;

        if ($lot->block) {
            $label = "Quadra {$lot->block} · {$label}";
        }

        return $label;
    }
}
