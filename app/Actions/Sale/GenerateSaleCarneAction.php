<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Installment;
use App\Models\Sale;
use App\Services\EfiService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class GenerateSaleCarneAction
{
    public function __construct(
        private readonly EfiService $efi,
    ) {}

    /**
     * @return array{
     *     carnet_id: int|string,
     *     pdf_carnet: string|null,
     *     pdf_cover: string|null,
     *     link: string|null,
     *     charges: int,
     *     first_due_date: string,
     *     adjusted_from_scheduled: bool
     * }
     */
    public function execute(Sale $sale, ?string $requestedFirstDueDate = null): array
    {
        if ($sale->efi_carnet_id !== null) {
            throw new InvalidArgumentException('Esta venda já possui carnê bancário gerado.');
        }

        $sale->loadMissing(['client', 'lot.development', 'installments']);

        $client = $sale->client;

        if ($client === null) {
            throw new InvalidArgumentException('Venda sem cliente vinculado.');
        }

        /** @var Collection<int, Installment> $unpaidInstallments */
        $unpaidInstallments = $sale->installments
            ->filter(fn (Installment $installment): bool => $installment->type !== Installment::TYPE_DOWN_PAYMENT)
            ->filter(fn (Installment $installment): bool => $installment->status !== Installment::STATUS_PAID)
            ->sortBy('number')
            ->values();

        if ($unpaidInstallments->isEmpty()) {
            throw new InvalidArgumentException('Não há parcelas de financiamento em aberto.');
        }

        $scheduledFirstDueDate = Carbon::parse($unpaidInstallments->first()->due_date)->startOfDay();
        [$firstDueDate, $adjustedFromScheduled] = $this->resolveFirstDueDate(
            $scheduledFirstDueDate,
            $requestedFirstDueDate,
        );

        $description = 'Lote '.($sale->lot?->number ?? '?')
            .' – '.($sale->lot?->development?->name ?? 'Sid360 Imóveis');

        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.$sale->sale_date?->format('Y');

        $carne = $this->efi->createCarne(
            installmentValueCents: (int) $sale->installment_value,
            installmentsCount: $unpaidInstallments->count(),
            firstDueDate: $firstDueDate,
            debtorName: (string) $client->name,
            debtorCpf: (string) $client->cpf,
            itemDescription: $description,
            debtorPhone: $client->phone,
            message: "Contrato {$contractNo} – Sid360 Imóveis",
        );

        $sale->update([
            'efi_carnet_id' => $carne['carnet_id'],
            'efi_carnet_pdf' => $carne['pdf_carnet'],
            'efi_carnet_link' => $carne['link'],
        ]);

        foreach ($carne['charges'] as $charge) {
            $installment = $unpaidInstallments->get(((int) $charge['parcel']) - 1);

            if ($installment) {
                $installment->update([
                    'efi_charge_id' => (string) $charge['charge_id'],
                    'efi_barcode' => $charge['barcode'],
                    'efi_pdf_url' => $charge['pdf'],
                    'efi_payment_type' => 'carne',
                ]);
            }
        }

        return [
            'carnet_id' => $carne['carnet_id'],
            'pdf_carnet' => $carne['pdf_carnet'],
            'pdf_cover' => $carne['pdf_cover'],
            'link' => $carne['link'],
            'charges' => count($carne['charges']),
            'first_due_date' => $firstDueDate,
            'adjusted_from_scheduled' => $adjustedFromScheduled,
        ];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function resolveFirstDueDate(Carbon $scheduledFirstDueDate, ?string $requested): array
    {
        $minimum = now()->addDay()->startOfDay();

        if ($requested !== null && $requested !== '') {
            $date = Carbon::parse($requested)->startOfDay();

            if ($date->lt($minimum)) {
                throw new InvalidArgumentException('A 1ª parcela do carnê deve vencer após hoje.');
            }

            return [$date->toDateString(), false];
        }

        if ($scheduledFirstDueDate->gte($minimum)) {
            return [$scheduledFirstDueDate->toDateString(), false];
        }

        return [$minimum->toDateString(), true];
    }
}
