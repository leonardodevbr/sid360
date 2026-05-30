<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use App\Services\EfiService;
use App\Support\EfiDebtorValidator;
use Efi\Exception\EfiException;
use InvalidArgumentException;
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

        $this->assertValueWithinEfiLimit((int) $charge['total_value']);

        $description = 'Contrato '.str_pad((string) $installment->sale_id, 4, '0', STR_PAD_LEFT)
            .' – Parcela '.$installment->number;

        $client = $installment->sale->client;
        $clientName = (string) $client->name;
        $debtorCpfDigits = EfiDebtorValidator::digitsOnlyCpf((string) $client->cpf);

        EfiDebtorValidator::assertValidCpf($debtorCpfDigits, $clientName);
        EfiDebtorValidator::assertNotConfiguredHolderCpf($debtorCpfDigits, $clientName);

        try {
            $boleto = $this->efi->createBoleto(
                valueInCents: (float) $charge['total_value'],
                debtorName: $clientName,
                debtorCpf: $debtorCpfDigits,
                dueDate: $dueDate,
                description: $description,
                debtorPhone: null,
                waivePenalties: $waivePenalties || $charge['total_value'] > $charge['original_value'],
            );
        } catch (EfiException $e) {
            if (EfiDebtorValidator::isSamePersonError($e)) {
                throw new InvalidArgumentException(EfiDebtorValidator::samePersonErrorMessage(
                    $debtorCpfDigits,
                    $clientName,
                    (int) ($e->code ?? 0),
                ));
            }

            if ($this->isValueLimitError($e)) {
                throw new InvalidArgumentException($this->valueLimitErrorMessage((int) $charge['total_value']));
            }

            throw $e;
        }

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

    private function assertValueWithinEfiLimit(int $valueCents): void
    {
        $maxCents = (int) config('services.efi.carne_max_value_cents', 200_000);

        if ($maxCents > 0 && $valueCents > $maxCents) {
            throw new InvalidArgumentException($this->valueLimitErrorMessage($valueCents, $maxCents));
        }
    }

    private function isValueLimitError(EfiException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return (int) ($e->code ?? 0) === 4600037
            || str_contains($message, 'valor máximo')
            || str_contains($message, 'limite operacional');
    }

    private function valueLimitErrorMessage(int $valueCents, ?int $maxCents = null): string
    {
        $maxCents ??= (int) config('services.efi.carne_max_value_cents', 200_000);

        return 'Valor da parcela ('
            .$this->formatMoney($valueCents)
            .') ultrapassa o limite da conta Efi ('
            .$this->formatMoney($maxCents)
            .' por boleto). Peça aumento do limite ao suporte Efí ou use PIX.';
    }

    private function formatMoney(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
