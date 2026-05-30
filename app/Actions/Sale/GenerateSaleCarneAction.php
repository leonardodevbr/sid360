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

        if (strlen($debtorCpfDigits) !== 11) {
            throw new InvalidArgumentException(
                'CPF do cliente inválido no cadastro ('
                .$this->formatCpf($debtorCpfDigits !== '' ? $debtorCpfDigits : 'vazio')
                .'). Corrija em Clientes antes de gerar o carnê.',
            );
        }

        $this->assertDebtorIsNotEfiHolder($debtorCpfDigits, (string) $client->name);
        $this->assertInstallmentValueWithinCarneLimit((int) $sale->installment_value);

        try {
            $carne = $this->efi->createCarne(
                installmentValueCents: (int) $sale->installment_value,
                installmentsCount: $unpaidInstallments->count(),
                firstDueDate: $firstDueDate,
                debtorName: (string) $client->name,
                debtorCpf: $debtorCpfDigits,
                itemDescription: $description,
                debtorPhone: null,
                message: "Contrato {$contractNo} – Sid360 Imóveis",
            );
        } catch (EfiException $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'mesma pessoa')) {
                throw new InvalidArgumentException($this->efiSamePersonErrorMessage(
                    $debtorCpfDigits,
                    (string) $client->name,
                    (int) ($e->code ?? 0),
                ));
            }

            if ($this->isCarneValueLimitError($e)) {
                throw new InvalidArgumentException($this->carneValueLimitErrorMessage((int) $sale->installment_value));
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
            throw new InvalidArgumentException($this->envHolderCpfErrorMessage($debtorCpfDigits, (string) $client->name));
        }
    }

    private function envHolderCpfErrorMessage(string $debtorCpfDigits, string $clientName): string
    {
        return 'CPF do cliente coincide com EFI_HOLDER_CPF do .env ('
            .$this->formatCpf($debtorCpfDigits)
            ."). Cliente «{$clientName}». "
            .'Esse valor no .env deve ser o SEU CPF (titular da conta Efi), não o CPF do cliente. '
            .'Remova EFI_HOLDER_CPF ou corrija para o CPF do titular.';
    }

    private function efiSamePersonErrorMessage(string $debtorCpfDigits, string $clientName, int $efiCode): string
    {
        $code = $efiCode > 0 ? " (código Efi {$efiCode})" : '';

        return 'A Efi rejeitou o carnê'.$code.': recebedor e cliente não podem ser a mesma pessoa. '
            ."CPF enviado ao pagador: {$this->formatCpf($debtorCpfDigits)} (cliente «{$clientName}»). "
            .'No cadastro da Efi, esse CPF está vinculado ao recebedor desta conta/API — confira no painel Efí '
            .'(Meus dados / titular ou sócio administrador) se o documento cadastrado é realmente o seu, '
            .'e se o Client_Id da API é da mesma conta.';
    }

    private function assertInstallmentValueWithinCarneLimit(int $installmentValueCents): void
    {
        $maxCents = (int) config('services.efi.carne_max_value_cents', 200_000);

        if ($maxCents > 0 && $installmentValueCents > $maxCents) {
            throw new InvalidArgumentException($this->carneValueLimitErrorMessage($installmentValueCents, $maxCents));
        }
    }

    private function isCarneValueLimitError(EfiException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return (int) ($e->code ?? 0) === 4600037
            || str_contains($message, 'valor máximo')
            || str_contains($message, 'limite operacional');
    }

    private function carneValueLimitErrorMessage(int $installmentValueCents, ?int $maxCents = null): string
    {
        $maxCents ??= (int) config('services.efi.carne_max_value_cents', 200_000);

        return 'Cada parcela do carnê Efi ('
            .$this->formatMoney($installmentValueCents)
            .') ultrapassa o limite da sua conta ('
            .$this->formatMoney($maxCents)
            .' por boleto). Esse teto é da conta Efi, não do Sid360. '
            .'Peça aumento do limite operacional ao suporte Efí ou gere boletos avulsos parcela a parcela.';
    }

    private function formatMoney(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
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
