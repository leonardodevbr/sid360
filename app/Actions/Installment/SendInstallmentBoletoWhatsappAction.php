<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Support\ClientDisplayName;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInstallmentBoletoWhatsappAction
{
    public function __construct(
        private readonly GenerateInstallmentBoletoAction $generateBoleto,
        private readonly WhatsappService $whatsapp,
    ) {}

    /**
     * @return array{ok: bool, text_sent: bool, pdf_sent: bool|null, error?: string}
     */
    public function execute(
        Installment $installment,
        string $phone,
        string $interactionType = InstallmentInteraction::TYPE_BOLETO,
        bool $regenerate = true,
    ): array {
        $installment->loadMissing(['sale.client']);

        $pdfUrl = trim((string) ($installment->efi_pdf_url ?? ''));

        if ($regenerate || $pdfUrl === '' || $installment->efi_payment_type !== 'boleto') {
            try {
                $this->generateBoleto->execute($installment);
                $installment->refresh();
            } catch (Throwable $e) {
                Log::error('SendInstallmentBoletoWhatsappAction: boleto generation failed', [
                    'installment_id' => $installment->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'ok' => false,
                    'text_sent' => false,
                    'pdf_sent' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $pdfUrl = trim((string) ($installment->efi_pdf_url ?? ''));

        if ($pdfUrl === '') {
            return ['ok' => false, 'text_sent' => false, 'pdf_sent' => null];
        }

        $sale = $installment->sale;
        $client = $sale->client;
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

        $parcelLabel = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'entrada'
            : 'parcela-'.$installment->number;

        $filename = "boleto-contrato-{$sale->id}-{$parcelLabel}.pdf";

        $message = $this->whatsapp->buildBoletoPaymentMessage(
            clientName: ClientDisplayName::short((string) $client->name),
            contractNo: $contractNo,
            installment: $installment,
            barcode: $installment->efi_barcode,
            pdfUrl: $pdfUrl,
        );

        $fileCaption = $this->whatsapp->buildBoletoFileCaption(
            contractNo: $contractNo,
            installment: $installment,
        );

        $result = $this->whatsapp->sendBoletoAndRecord(
            phone: $phone,
            message: $message,
            filename: $filename,
            type: $interactionType,
            pdfUrl: $pdfUrl,
            installmentId: $installment->id,
            saleId: $installment->sale_id,
            clientId: $client->id,
            fileCaption: $fileCaption,
        );

        return [
            'ok' => $result['text_sent'],
            'text_sent' => $result['text_sent'],
            'pdf_sent' => $result['file_sent'],
        ];
    }
}
