<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Installment\CalculateInstallmentChargeValueAction;
use App\Actions\Installment\GenerateInstallmentBoletoAction;
use App\Actions\Installment\GenerateInstallmentPixAction;
use App\Actions\Installment\SendInstallmentBoletoWhatsappAction;
use App\Actions\Installment\SendInstallmentPixWhatsappAction;
use App\Actions\Sale\GenerateSaleCarneAction;
use App\Actions\Sale\PreviewSaleCarneDebtorAction;
use App\Http\Requests\GenerateInstallmentBoletoRequest;
use App\Http\Requests\GenerateInstallmentPixRequest;
use App\Http\Requests\GenerateSaleCarneRequest;
use App\Models\Installment;
use App\Models\Sale;
use App\Services\EfiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EfiPaymentController extends Controller
{
    public function __construct(
        private readonly EfiService $efi,
        private readonly GenerateInstallmentPixAction $generatePix,
        private readonly GenerateInstallmentBoletoAction $generateBoleto,
        private readonly SendInstallmentPixWhatsappAction $sendPixWhatsapp,
        private readonly SendInstallmentBoletoWhatsappAction $sendBoletoWhatsapp,
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
    ): JsonResponse {
        $installment = Installment::query()
            ->with(['sale.client'])
            ->findOrFail((int) $installmentId);

        if ($installment->status === Installment::STATUS_PAID) {
            return response()->json(['error' => 'Parcela já paga.'], 422);
        }

        try {
            $waivePenalties = $request->boolean('waive_penalties');
            $expiry = (int) ($request->input('expiry_seconds') ?? config('services.efi.pix_expiry', 3600));

            $result = $this->generatePix->execute(
                installment: $installment,
                waivePenalties: $waivePenalties,
                expirySeconds: $expiry,
            );

            return response()->json([
                'txid' => $result['txid'],
                'pix_copia_cola' => $result['pix_copia_cola'],
                'qrcode' => $result['qrcode'],
                'charge_value' => $result['charge_value'],
                'charge_breakdown' => $result['charge_breakdown'],
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

        $client = $installment->sale?->client;
        $phone = trim((string) ($client?->phone ?? ''));

        if ($phone === '') {
            return response()->json([
                'error' => 'Cliente sem telefone/WhatsApp cadastrado.',
            ], 422);
        }

        $sent = $this->sendPixWhatsapp->execute(
            installment: $installment,
            phone: $phone,
            regenerate: trim((string) ($installment->efi_pix_copia_cola ?? '')) === '',
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

        $client = $installment->sale?->client;
        $phone = trim((string) ($client?->phone ?? ''));

        if ($phone === '') {
            return response()->json([
                'error' => 'Cliente sem telefone/WhatsApp cadastrado.',
            ], 422);
        }

        $pdfUrl = trim((string) ($installment->efi_pdf_url ?? ''));
        $regenerate = $pdfUrl === '' || $installment->efi_payment_type !== 'boleto';

        $result = $this->sendBoletoWhatsapp->execute(
            installment: $installment,
            phone: $phone,
            regenerate: $regenerate,
        );

        if (! $result['ok']) {
            return response()->json([
                'error' => 'Não foi possível enviar pelo WhatsApp automático.',
                'fallback' => true,
            ], 503);
        }

        return response()->json([
            'ok' => true,
            'text_sent' => true,
            'pdf_sent' => $result['pdf_sent'] === true,
            'warning' => $result['pdf_sent'] === false
                ? 'Mensagem enviada, mas o PDF não pôde ser anexado.'
                : null,
        ]);
    }

    public function generateBoleto(
        GenerateInstallmentBoletoRequest $request,
        string|int $installmentId,
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
                $dueDate = null;
            }

            $result = $this->generateBoleto->execute(
                installment: $installment,
                waivePenalties: $waivePenalties,
                dueDate: $dueDate,
            );

            return response()->json([
                'charge_id' => $result['charge_id'],
                'barcode' => $result['barcode'],
                'pdf' => $result['pdf'],
                'link' => $result['link'],
                'due_date' => $result['due_date'],
                'charge_value' => $result['charge_value'],
                'charge_breakdown' => $result['charge_breakdown'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar boleto: '.$e->getMessage()], 500);
        }
    }

    public function generateCarne(GenerateSaleCarneRequest $request, string|int $saleId, GenerateSaleCarneAction $action): JsonResponse
    {
        $sale = Sale::query()->findOrFail((int) $saleId);

        try {
            return response()->json(
                $action->execute(
                    sale: $sale,
                    requestedFirstDueDate: $request->validated('first_due_date'),
                ),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar carnê bancário: '.$e->getMessage()], 500);
        }
    }

    public function previewCarneDebtor(string|int $saleId, PreviewSaleCarneDebtorAction $action): JsonResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->findOrFail((int) $saleId);

        try {
            return response()->json($action->execute($sale));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
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
}
