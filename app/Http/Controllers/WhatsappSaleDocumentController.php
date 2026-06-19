<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Installment\GenerateInstallmentReciboPdfAction;
use App\Actions\Sale\GenerateSaleCarnePdfAction;
use App\Actions\Sale\GenerateSaleContractPdfAction;
use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Http\Response;

class WhatsappSaleDocumentController extends Controller
{
    public function contract(string|int $id, GenerateSaleContractPdfAction $action): Response
    {
        $sale = Sale::query()
            ->with(['client', 'lot.development', 'lot.street', 'lot.zone.parent', 'buyers'])
            ->findOrFail((int) $id);

        $pdfBytes = $action->execute($sale);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato-venda-'.$sale->id.'.pdf"',
        ]);
    }

    public function carne(string|int $id, GenerateSaleCarnePdfAction $action): Response
    {
        $sale = Sale::query()->findOrFail((int) $id);

        $pdfBytes = $action->execute($sale);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="promissoria-venda-'.$sale->id.'.pdf"',
        ]);
    }

    public function recibo(string|int $id, GenerateInstallmentReciboPdfAction $action): Response
    {
        $installment = Installment::query()->findOrFail((int) $id);

        $pdfBytes = $action->execute($installment);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$action->filename($installment).'"',
        ]);
    }
}
