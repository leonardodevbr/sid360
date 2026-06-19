<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use App\Support\ContractParty;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class GenerateInstallmentReciboPdfAction
{
    /**
     * Gera o PDF de recibo de uma parcela/entrada já paga. Reaproveita os
     * mesmos dados de cabeçalho (empresa/vendedor/foro) usados no
     * contrato e no carnê, vindos de Settings via ContractParty.
     */
    public function execute(Installment $installment): string
    {
        $installment->loadMissing(['sale.client', 'sale.lot.development', 'sale.buyers']);

        if ($installment->status !== Installment::STATUS_PAID) {
            throw new RuntimeException('Esta parcela ainda não foi paga — não é possível emitir o recibo.');
        }

        return (string) Pdf::loadView('pdf.recibo', [
            'installment' => $installment,
            'company' => ContractParty::company(),
            'seller' => ContractParty::seller($installment->sale->lot->development ?? null),
            'foro' => ContractParty::foro(),
        ])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function filename(Installment $installment): string
    {
        $parcelLabel = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'entrada'
            : 'parcela-'.$installment->number;

        return "recibo-contrato-{$installment->sale_id}-{$parcelLabel}.pdf";
    }
}
