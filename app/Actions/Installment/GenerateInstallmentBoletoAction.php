<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use App\Services\EfiService;
use Throwable;

class GenerateInstallmentBoletoAction
{
    public function __construct(
        private readonly EfiService $efi,
        private readonly CalculateInstallmentChargeValueAction $calculateCharge,
    ) {}

    /**
     * @return array{
     *     charge_id: string|int,
     *     barcode: string|null,
     *     pdf: string|null,
     *     link: string|null,
     *     due_date: string,
     *     charge_value: float,
     *     charge_breakdown: array<string, mixed>
     * }
     *
     * @throws Throwable
     */
    public function execute(
        Installment $installment,
        bool $waivePenalties = false,
        ?string $dueDate = null,
    ): array {
        $installment->loadMissing(['sale.client']);

        if ($installment->status === Installment::STATUS_PAID) {
            throw new \InvalidArgumentException('Parcela já paga.');
        }

        if ($dueDate === null || $dueDate === '') {
            $dueDate = $installment->due_date->gte(now()->startOfDay())
                ? $installment->due_date->toDateString()
                : now()->addDay()->toDateString();
        }

        $charge = $this->calculateCharge->execute($installment, $waivePenalties, $dueDate);

        $description = 'Contrato '.str_pad((string) $installment->sale_id, 4, '0', STR_PAD_LEFT)
            .' – Parcela '.$installment->number;

        $boleto = $this->efi->createBoleto(
            valueInCents: (float) $charge['total_value'],
            debtorName: (string) $installment->sale->client->name,
            debtorCpf: (string) $installment->sale->client->cpf,
            dueDate: $dueDate,
            description: $description,
            debtorPhone: $installment->sale->client->phone,
            waivePenalties: $waivePenalties || $charge['total_value'] > $charge['original_value'],
        );

        $installment->update([
            'efi_charge_id' => (string) $boleto['charge_id'],
            'efi_barcode' => $boleto['barcode'],
            'efi_pdf_url' => $boleto['pdf'],
            'efi_payment_type' => 'boleto',
        ]);

        return [
            'charge_id' => $boleto['charge_id'],
            'barcode' => $boleto['barcode'],
            'pdf' => $boleto['pdf'],
            'link' => $boleto['link'],
            'due_date' => $dueDate,
            'charge_value' => (float) $charge['total_value'],
            'charge_breakdown' => $charge,
        ];
    }
}
