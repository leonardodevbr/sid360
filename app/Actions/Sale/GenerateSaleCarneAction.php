<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Installment;
use App\Models\Sale;
use App\Services\EfiService;
use App\Support\DocumentHelper;
use Carbon\Carbon;
use Efi\Exception\EfiException;
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

        $debtorCpfDigits = DocumentHelper::digitsOnly((string) $client->cpf);
        $this->assertDebtorIsNotEfiHolder($debtorCpfDigits, (string) $client->name);

        try {
            $carne = $this->efi->createCarne(
                installmentValueCents: (int) $sale->installment_value,
                installmentsCount: $unpaidInstallments->count(),
                firstDueDate: $firstDueDate,
                debtorName: (string) $client->name,
                debtorCpf: $debtorCpfDigits,
                itemDescription: $description,
                debtorPhone: $client->phone,
                message: "Contrato {$contractNo} – Sid360 Imóveis",
            );
        } catch (EfiException $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'mesma pessoa')) {
                throw new InvalidArgumentException($this->samePersonErrorMessage($debtorCpfDigits, (string) $client->name));
            }

            throw $e;
        }

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

    private function assertDebtorIsNotEfiHolder(string $debtorCpfDigits, string $clientName): void
    {
        $holderCpfDigits = DocumentHelper::digitsOnly((string) config('services.efi.holder_cpf', ''));

        if ($holderCpfDigits !== '' && $holderCpfDigits === $debtorCpfDigits) {
            throw new InvalidArgumentException($this->samePersonErrorMessage($debtorCpfDigits, $clientName));
        }
    }

    private function samePersonErrorMessage(string $debtorCpfDigits, string $clientName): string
    {
        return 'Recebedor e cliente não podem ser a mesma pessoa. '
            ."CPF enviado: {$this->formatCpf($debtorCpfDigits)} (cliente «{$clientName}», cadastro clients.cpf da venda). "
            .'Esse CPF não pode ser igual ao titular da conta Efi. Co-compradores não entram no carnê.';
    }

    private function formatCpf(string $digits): string
    {
        if (strlen($digits) !== 11) {
            return $digits;
        }

        return substr($digits, 0, 3).'.'
            .substr($digits, 3, 3).'.'
            .substr($digits, 6, 3).'-'
            .substr($digits, 9, 2);
    }
}
