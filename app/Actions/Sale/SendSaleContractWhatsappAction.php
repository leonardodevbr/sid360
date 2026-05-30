<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

class SendSaleContractWhatsappAction
{
    public function __construct(
        private readonly BuildWhatsappSaleDocumentUrlAction $documentUrl,
        private readonly GenerateSaleContractPdfAction $generateContract,
        private readonly WhatsappService $whatsapp,
    ) {}

    public function execute(Sale $sale, string $phone, string $interactionType): bool
    {
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

        $filename = "contrato-venda-{$sale->id}.pdf";
        $caption = "Contrato {$contractNo} — Sid360 Imóveis";

        $signedUrl = $this->documentUrl->contractUrl($sale);

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
            $pdfBytes = $this->generateContract->execute($sale);
        } catch (\Throwable $e) {
            Log::error('SendSaleContractWhatsappAction: PDF generation failed', [
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
