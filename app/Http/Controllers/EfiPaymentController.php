<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Installment\CalculateInstallmentChargeValueAction;
use App\Http\Requests\GenerateInstallmentBoletoRequest;
use App\Http\Requests\GenerateInstallmentPixRequest;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Services\EfiService;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EfiPaymentController extends Controller
{
    public function __construct(
        private readonly EfiService $efi,
        private readonly WhatsappService $whatsapp,
    ) {}

    public function chargePreview(
        string|int $installmentId,
        CalculateInstallmentChargeValueAction $calculateCharge,
    ): JsonResponse {
        $this->authorize('sales.view');

        $installment = Installment::query()->findOrFail((int) $installmentId);

        $waivePenalties = filter_var(request()->input('waive_penalties', false), FILTER_VALIDATE_BOOLEAN);
        $referenceDate = request()->input('reference_date');

        return response()->json(
            $calculateCharge->execute(
                installment: $installment,
                waivePenalties: $waivePenalties,
                referenceDate: is_string($referenceDate) ? $referenceDate : null,
            ),
        );
    }

    public function generatePix(
        GenerateInstallmentPixRequest $request,
        string|int $installmentId,
        CalculateInstallmentChargeValueAction $calculateCharge,
    ): JsonResponse {
        $installment = Installment::query()
            ->with(['sale.client'])
            ->findOrFail((int) $installmentId);

        if ($installment->status === Installment::STATUS_PAID) {
            return response()->json(['error' => 'Parcela já paga.'], 422);
        }

        try {
            $waivePenalties = $request->boolean('waive_penalties');
            $charge = $calculateCharge->execute($installment, $waivePenalties);
            $expiry = (int) ($request->input('expiry_seconds') ?? config('services.efi.pix_expiry', 3600));
            $reference = 'Contrato '.str_pad((string) $installment->sale_id, 4, '0', STR_PAD_LEFT)
                .' – Parcela '.$installment->number;

            $pix = $this->efi->createPixCharge(
                valueInCents: (float) $charge['total_value'],
                debtorName: (string) $installment->sale->client->name,
                debtorCpf: (string) $installment->sale->client->cpf,
                reference: $reference,
                expirySeconds: $expiry,
            );

            $qrCode = $this->efi->getPixQrCode((int) $pix['loc_id']);
            $qrcode = $this->normalizePixQrCodeImage($qrCode['image']);
            $pixCopiaCola = $qrCode['copy_paste'] ?? $pix['pix_copia_cola'];

            $installment->update([
                'efi_txid' => $pix['txid'],
                'efi_pix_copia_cola' => $pixCopiaCola,
                'efi_pix_qrcode' => $qrcode,
                'efi_payment_type' => 'pix',
            ]);

            return response()->json([
                'txid' => $pix['txid'],
                'pix_copia_cola' => $pixCopiaCola,
                'qrcode' => $qrcode,
                'charge_value' => $charge['total_value'],
                'charge_breakdown' => $charge,
                'expiry_seconds' => $expiry,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar PIX: '.$e->getMessage()], 500);
        }
    }

    public function sendPixWhatsApp(string|int $installmentId): JsonResponse
    {
        $installment = Installment::query()
            ->with(['sale.client'])
            ->findOrFail((int) $installmentId);

        $pixCode = trim((string) ($installment->efi_pix_copia_cola ?? ''));

        if ($pixCode === '') {
            return response()->json([
                'error' => 'Código PIX não disponível. Gere o PIX antes de enviar.',
            ], 422);
        }

        $client = $installment->sale?->client;
        $phone = trim((string) ($client?->phone ?? ''));

        if ($phone === '') {
            return response()->json([
                'error' => 'Cliente sem telefone/WhatsApp cadastrado.',
            ], 422);
        }

        $sale = $installment->sale;
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

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

        $sent = $this->whatsapp->sendPixAndRecord(
            phone: $phone,
            message: $message,
            qrCodeImage: $installment->efi_pix_qrcode,
            type: InstallmentInteraction::TYPE_PIX,
            installmentId: $installment->id,
            saleId: $installment->sale_id,
            clientId: $client->id,
            imageCaption: $imageCaption,
        );

        if (! $sent) {
            return response()->json([
                'error' => 'Não foi possível enviar pelo WhatsApp automático.',
                'fallback' => true,
            ], 503);
        }

        return response()->json(['ok' => true]);
    }

    public function sendBoletoWhatsApp(string|int $installmentId): JsonResponse
    {
        $installment = Installment::query()
            ->with(['sale.client'])
            ->findOrFail((int) $installmentId);

        $pdfUrl = trim((string) ($installment->efi_pdf_url ?? ''));

        if ($pdfUrl === '' || $installment->efi_payment_type !== 'boleto') {
            return response()->json([
                'error' => 'Boleto não disponível. Gere o boleto antes de enviar.',
            ], 422);
        }

        $client = $installment->sale?->client;
        $phone = trim((string) ($client?->phone ?? ''));

        if ($phone === '') {
            return response()->json([
                'error' => 'Cliente sem telefone/WhatsApp cadastrado.',
            ], 422);
        }

        $sale = $installment->sale;
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

        $parcelLabel = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'entrada'
            : 'parcela-'.$installment->number;

        $filename = "boleto-contrato-{$sale->id}-{$parcelLabel}.pdf";

        $message = $this->whatsapp->buildBoletoPaymentMessage(
            clientName: (string) $client->name,
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
            type: InstallmentInteraction::TYPE_BOLETO,
            pdfUrl: $pdfUrl,
            installmentId: $installment->id,
            saleId: $installment->sale_id,
            clientId: $client->id,
            fileCaption: $fileCaption,
        );

        if (! $result['text_sent']) {
            return response()->json([
                'error' => 'Não foi possível enviar pelo WhatsApp automático.',
                'fallback' => true,
            ], 503);
        }

        return response()->json([
            'ok' => true,
            'text_sent' => true,
            'pdf_sent' => $result['file_sent'] === true,
            'warning' => $result['file_sent'] === false
                ? 'Mensagem enviada, mas o PDF não pôde ser anexado.'
                : null,
        ]);
    }

    public function generateBoleto(
        GenerateInstallmentBoletoRequest $request,
        string|int $installmentId,
        CalculateInstallmentChargeValueAction $calculateCharge,
    ): JsonResponse {
        $installment = Installment::query()
            ->with(['sale.client'])
            ->findOrFail((int) $installmentId);

        if ($installment->status === Installment::STATUS_PAID) {
            return response()->json(['error' => 'Parcela já paga.'], 422);
        }

        try {
            $waivePenalties = $request->boolean('waive_penalties');
            $dueDate = $request->input('due_date');

            if (! is_string($dueDate) || $dueDate === '') {
                $dueDate = $installment->due_date->gte(now()->startOfDay())
                    ? $installment->due_date->toDateString()
                    : now()->addDay()->toDateString();
            }

            $charge = $calculateCharge->execute($installment, $waivePenalties, $dueDate);

            $description = 'Contrato '.str_pad((string) $installment->sale_id, 4, '0', STR_PAD_LEFT)
                .' – Parcela '.$installment->number;

            $boleto = $this->efi->createBoleto(
                valueInCents: (float) $charge['total_value'],
                debtorName: (string) $installment->sale->client->name,
                debtorCpf: (string) $installment->sale->client->cpf,
                dueDate: $dueDate,
                description: $description,
                debtorPhone: $installment->sale->client->phone,
                waivePenalties: $waivePenalties || $charge['total_value'] > $charge['original_value'],
            );

            $installment->update([
                'efi_charge_id' => (string) $boleto['charge_id'],
                'efi_barcode' => $boleto['barcode'],
                'efi_pdf_url' => $boleto['pdf'],
                'efi_payment_type' => 'boleto',
            ]);

            return response()->json([
                'charge_id' => $boleto['charge_id'],
                'barcode' => $boleto['barcode'],
                'pdf' => $boleto['pdf'],
                'link' => $boleto['link'],
                'due_date' => $dueDate,
                'charge_value' => $charge['total_value'],
                'charge_breakdown' => $charge,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar boleto: '.$e->getMessage()], 500);
        }
    }

    public function generateCarne(string|int $saleId): JsonResponse
    {
        $sale = Sale::query()
            ->with([
                'client',
                'lot.development',
                'installments' => fn ($query) => $query
                    ->where('type', '!=', Installment::TYPE_DOWN_PAYMENT)
                    ->orderBy('number'),
            ])
            ->findOrFail((int) $saleId);

        $client = $sale->client;
        $installments = $sale->installments;

        if ($installments->isEmpty()) {
            return response()->json(['error' => 'Venda sem parcelas de financiamento.'], 422);
        }

        try {
            $description = 'Lote '.($sale->lot?->number ?? '?')
                .' – '.($sale->lot?->development?->name ?? 'Sid360 Imóveis');

            $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
                .'/'.$sale->sale_date?->format('Y');

            $carne = $this->efi->createCarne(
                installmentValueCents: (int) $sale->installment_value,
                installmentsCount: (int) $sale->installments_count,
                firstDueDate: $sale->first_due_date->toDateString(),
                debtorName: (string) $client->name,
                debtorCpf: (string) $client->cpf,
                itemDescription: $description,
                debtorPhone: $client->phone,
                message: "Contrato {$contractNo} – Sid360 Imóveis",
            );

            $sale->update([
                'efi_carnet_id' => $carne['carnet_id'],
                'efi_carnet_pdf' => $carne['pdf_carnet'],
                'efi_carnet_link' => $carne['link'],
            ]);

            foreach ($carne['charges'] as $charge) {
                $installment = $installments->firstWhere('number', $charge['parcel']);

                if ($installment) {
                    $installment->update([
                        'efi_charge_id' => (string) $charge['charge_id'],
                        'efi_barcode' => $charge['barcode'],
                        'efi_pdf_url' => $charge['pdf'],
                        'efi_payment_type' => 'carne',
                    ]);
                }
            }

            return response()->json([
                'carnet_id' => $carne['carnet_id'],
                'pdf_carnet' => $carne['pdf_carnet'],
                'pdf_cover' => $carne['pdf_cover'],
                'link' => $carne['link'],
                'charges' => count($carne['charges']),
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar carnê bancário: '.$e->getMessage()], 500);
        }
    }

    public function pixWebhook(Request $request): JsonResponse
    {
        Log::info('EFI PIX Webhook', ['payload' => $request->all()]);

        $pixes = $request->input('pix', []);

        if (! is_array($pixes)) {
            return response()->json(['ok' => true]);
        }

        foreach ($pixes as $pix) {
            if (! is_array($pix)) {
                continue;
            }

            $txid = $pix['txid'] ?? null;

            if (! $txid) {
                continue;
            }

            $installment = Installment::query()
                ->where('efi_txid', $txid)
                ->first();

            if (! $installment) {
                continue;
            }

            $installment->update([
                'status' => Installment::STATUS_PAID,
                'paid_at' => now()->toDateString(),
            ]);

            Log::info('EFI PIX confirmado', ['txid' => $txid, 'installment_id' => $installment->id]);
        }

        return response()->json(['ok' => true]);
    }

    public function cobrancasWebhook(Request $request): JsonResponse
    {
        Log::info('EFI Cobranças Webhook', ['payload' => $request->all()]);

        $chargeId = $request->input('notification')
            ?? $request->input('charge_id');

        if (! $chargeId) {
            $token = $request->input('notification')
                ?? $request->query('notification');

            if (is_string($token) && $token !== '') {
                try {
                    $detail = $this->efi->getCobrancaNotification($token);

                    foreach ($detail['data'] ?? [] as $item) {
                        if (is_array($item)) {
                            $this->processCobrancaNotification($item);
                        }
                    }
                } catch (Throwable $e) {
                    Log::error('EFI Webhook notification error', ['error' => $e->getMessage()]);
                }
            }

            return response()->json(['ok' => true]);
        }

        $installment = Installment::query()
            ->where('efi_charge_id', (string) $chargeId)
            ->first();

        if ($installment) {
            $installment->update([
                'status' => Installment::STATUS_PAID,
                'paid_at' => now()->toDateString(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function processCobrancaNotification(array $item): void
    {
        $chargeId = $item['id'] ?? null;
        $status = $item['status'] ?? null;

        if (! $chargeId || $status !== 'paid') {
            return;
        }

        $installment = Installment::query()
            ->where('efi_charge_id', (string) $chargeId)
            ->first();

        if ($installment) {
            $installment->update([
                'status' => Installment::STATUS_PAID,
                'paid_at' => now()->toDateString(),
            ]);
        }
    }

    private function normalizePixQrCodeImage(string $qrcode): string
    {
        if ($qrcode === '') {
            return '';
        }

        if (str_starts_with($qrcode, 'data:')) {
            $pos = strpos($qrcode, ',');

            if ($pos !== false) {
                return substr($qrcode, $pos + 1);
            }
        }

        return $qrcode;
    }
}
