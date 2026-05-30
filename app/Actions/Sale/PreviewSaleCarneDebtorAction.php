<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Installment;
use App\Models\Sale;
use App\Support\DocumentHelper;
use InvalidArgumentException;

class PreviewSaleCarneDebtorAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Sale $sale): array
    {
        $sale->loadMissing(['client', 'buyers', 'installments']);

        $client = $sale->client;

        if ($client === null) {
            throw new InvalidArgumentException('Venda sem cliente vinculado.');
        }

        $cpfDigits = DocumentHelper::digitsOnly((string) $client->cpf);
        $holderCpfDigits = DocumentHelper::digitsOnly((string) config('services.efi.holder_cpf', ''));

        $unpaidCount = $sale->installments
            ->filter(fn (Installment $installment): bool => $installment->type !== Installment::TYPE_DOWN_PAYMENT)
            ->filter(fn (Installment $installment): bool => $installment->status !== Installment::STATUS_PAID)
            ->count();

        return [
            'sale_id' => $sale->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'cpf_in_database' => $client->cpf,
            'cpf_sent_to_efi' => $cpfDigits,
            'cpf_length' => strlen($cpfDigits),
            'phone_sent_to_efi' => DocumentHelper::digitsOnly((string) $client->phone),
            'unpaid_installments' => $unpaidCount,
            'efi_sandbox' => (bool) config('services.efi.sandbox', true),
            'holder_cpf_configured' => $holderCpfDigits !== '',
            'matches_configured_holder_cpf' => $holderCpfDigits !== '' && $holderCpfDigits === $cpfDigits,
            'co_buyers_not_sent_to_efi' => $sale->buyers
                ->reject(fn ($buyer): bool => (int) $buyer->id === (int) $client->id)
                ->map(fn ($buyer): array => [
                    'id' => $buyer->id,
                    'name' => $buyer->name,
                    'cpf' => $buyer->cpf,
                ])
                ->values()
                ->all(),
            'note' => 'O carnê usa apenas o cliente principal (sales.client_id). A Efi compara customer.cpf com o titular da conta recebedora (CPF do sócio, se conta PJ).',
        ];
    }
}
