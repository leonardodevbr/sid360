<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

class SendSaleCarneWhatsappAction
{
    public function __construct(
        private readonly BuildWhatsappSaleDocumentUrlAction $documentUrl,
        private readonly GenerateSaleCarnePdfAction $generateCarne,
        private readonly WhatsappService $whatsapp,
    ) {}

    public function execute(Sale $sale, string $phone, string $interactionType): bool
    {
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

        $filename = "promissoria-venda-{$sale->id}.pdf";
        $caption = "Carnê / promissória {$contractNo} — Sid360 Imóveis";

        $signedUrl = $this->documentUrl->carneUrl($sale);

        $sent = $this->whatsapp->sendDocument(
            phone: $phone,
            filename: $filename,
            caption: $caption,
            fileUrl: $signedUrl,
        );

        if ($sent) {
            return true;
        }

        try {
            $pdfBytes = $this->generateCarne->execute($sale);
        } catch (\Throwable $e) {
            Log::error('SendSaleCarneWhatsappAction: PDF generation failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return $this->whatsapp->sendDocument(
            phone: $phone,
            filename: $filename,
            caption: $caption,
            base64File: base64_encode($pdfBytes),
        );
    }
}
