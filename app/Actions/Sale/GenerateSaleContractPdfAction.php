<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class GenerateSaleContractPdfAction
{
    /**
     * Monta um nome de arquivo que identifica o cliente e o lote/loteamento,
     * em vez do genérico "contrato-venda-{id}.pdf".
     */
    public function filename(Sale $sale, bool $isDraft = false): string
    {
        $sale->loadMissing(['client', 'lot.development']);

        $clientSlug = $this->abbreviate($sale->client?->name);
        $developmentSlug = $this->abbreviate($sale->lot?->development?->name, 3);
        $lotSlug = Str::slug((string) ($sale->lot?->number ?? ''), '-');

        $parts = array_filter([
            $clientSlug,
            $developmentSlug,
            $lotSlug !== '' ? "lote-{$lotSlug}" : null,
        ]);

        $prefix = $isDraft ? 'minuta' : 'contrato';
        $name = trim($prefix . '-' . implode('-', $parts) . '-' . $sale->id, '-');

        return Str::slug($name, '-') . '.pdf';
    }

    /**
     * Reduz um nome a suas primeiras palavras (ex.: "João Pedro de Souza" -> "joao-pedro"),
     * o suficiente para identificar a quem o arquivo se refere sem ficar gigante.
     */
    private function abbreviate(?string $name, int $words = 2): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $short = implode(' ', array_slice($parts, 0, $words));

        return Str::slug($short, '-');
    }

    public function execute(Sale $sale, bool $isDraft = false): string
    {
        $sale->loadMissing(['client', 'lot.development', 'lot.street', 'lot.zone.parent', 'buyers']);

        $pdf = Pdf::loadView('pdf.contract', ['sale' => $sale, 'isDraft' => $isDraft])
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
