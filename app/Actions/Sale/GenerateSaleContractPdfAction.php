<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateSaleContractPdfAction
{
    public function execute(Sale $sale): string
    {
        $sale->loadMissing(['client', 'lot.development', 'lot.street', 'lot.zone.parent', 'buyers']);

        $pdf = Pdf::loadView('pdf.contract', ['sale' => $sale])
            ->setPaper('a4', 'portrait');

        $pdf->render();

        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $font = $fontMetrics->getFont('Times-Roman', 'normal');

        $pdf->getCanvas()->page_script(function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($font): void {
            $text = "{$pageNumber}/{$pageCount}";
            $size = 9;
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $x = ($canvas->get_width() - $width) / 2;
            $y = $canvas->get_height() - 55;

            $canvas->text($x, $y, $text, $font, $size, [0.35, 0.35, 0.35]);
        });

        return (string) $pdf->output();
    }
}
