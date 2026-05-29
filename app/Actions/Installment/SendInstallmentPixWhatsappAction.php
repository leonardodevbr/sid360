<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInstallmentPixWhatsappAction
{
    public function __construct(
        private readonly GenerateInstallmentPixAction $generatePix,
        private readonly WhatsappService $whatsapp,
    ) {}

    public function execute(
        Installment $installment,
        string $phone,
        string $interactionType = InstallmentInteraction::TYPE_PIX,
        bool $regenerate = true,
    ): bool {
        $installment->loadMissing(['sale.client']);

        $pixCode = trim((string) ($installment->efi_pix_copia_cola ?? ''));

        if ($regenerate || $pixCode === '') {
            try {
                $this->generatePix->execute($installment);
                $installment->refresh();
            } catch (Throwable $e) {
                Log::error('SendInstallmentPixWhatsappAction: PIX generation failed', [
                    'installment_id' => $installment->id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        $pixCode = trim((string) ($installment->efi_pix_copia_cola ?? ''));

        if ($pixCode === '') {
            return false;
        }

        $sale = $installment->sale;
        $client = $sale->client;
        $contractNo = $this->contractNumber($sale);

        $message = $this->whatsapp->buildPixPaymentMessage(
            clientName: (string) $client->name,
            contractNo: $contractNo,
            installment: $installment,
            pixCopyPaste: $pixCode,
        );

        $imageCaption = $this->whatsapp->buildPixImageCaption(
            contractNo: $contractNo,
            installment: $installment,
        );

        return $this->whatsapp->sendPixAndRecord(
            phone: $phone,
            message: $message,
            qrCodeImage: $installment->efi_pix_qrcode,
            type: $interactionType,
            installmentId: $installment->id,
            saleId: $installment->sale_id,
            clientId: $client->id,
            imageCaption: $imageCaption,
        );
    }

    private function contractNumber(Sale $sale): string
    {
        return str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));
    }
}
