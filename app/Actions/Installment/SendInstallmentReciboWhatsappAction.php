<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Actions\Sale\BuildWhatsappSaleDocumentUrlAction;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Services\WhatsappService;
use App\Support\ClientDisplayName;
use RuntimeException;

class SendInstallmentReciboWhatsappAction
{
    public function __construct(
        private readonly BuildWhatsappSaleDocumentUrlAction $buildUrl,
        private readonly WhatsappService $whatsapp,
    ) {}

    /**
     * @return array{ok: bool, text_sent: bool, pdf_sent: bool|null, error?: string}
     */
    public function execute(Installment $installment, string $phone): array
    {
        $installment->loadMissing(['sale.client']);

        if ($installment->status !== Installment::STATUS_PAID) {
            throw new RuntimeException('Esta parcela ainda não foi paga — não é possível enviar o recibo.');
        }

        $sale = $installment->sale;
        $client = $sale->client;
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

        $parcelLabel = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'entrada'
            : 'parcela-'.$installment->number;

        $filename = "recibo-contrato-{$sale->id}-{$parcelLabel}.pdf";
        $pdfUrl = $this->buildUrl->reciboUrl($installment);

        $message = $this->whatsapp->buildReciboMessage(
            clientName: ClientDisplayName::short((string) $client->name),
            contractNo: $contractNo,
            installment: $installment,
        );

        $fileCaption = $this->whatsapp->buildReciboFileCaption(
            contractNo: $contractNo,
            installment: $installment,
        );

        $result = $this->whatsapp->sendBoletoAndRecord(
            phone: $phone,
            message: $message,
            filename: $filename,
            type: InstallmentInteraction::TYPE_RECIBO,
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
