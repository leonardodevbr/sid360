<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GenerateSaleCarnePdfAction
{
    public function execute(Sale $sale): string
    {
        $sale->loadMissing([
            'client',
            'lot.development',
            'buyers',
            'financingInstallments',
        ]);

        if ($sale->installments_count < 1 || $sale->financingInstallments->isEmpty()) {
            throw new NotFoundHttpException('Esta venda não possui parcelas para carnê.');
        }

        $sale->setRelation('installments', $sale->financingInstallments);

        return (string) Pdf::loadView('pdf.carne', ['sale' => $sale])
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
