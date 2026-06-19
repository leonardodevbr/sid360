<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Installment\GenerateInstallmentReciboPdfAction;
use App\Actions\Installment\PayInstallmentAction;
use App\Actions\Installment\SendInstallmentReciboWhatsappAction;
use App\Actions\Installment\UpdateInstallmentDueDateAction;
use App\Http\Requests\PayInstallmentRequest;
use App\Http\Requests\UpdateInstallmentDueDateRequest;
use App\Http\Resources\InstallmentResource;
use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InstallmentController extends Controller
{
    public function bySale(string|int $saleId): AnonymousResourceCollection
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->findOrFail((int) $saleId);

        return InstallmentResource::collection(
            $sale->installments()->orderBy('number')->get()
        );
    }

    public function pay(PayInstallmentRequest $request, string|int $id, PayInstallmentAction $action): JsonResponse
    {
        $installment = Installment::query()->findOrFail((int) $id);
        $installment = $action->execute(
            $installment,
            $request->validated('paid_at'),
            $request->validated('payment_method'),
            $request->validated('payment_method_description'),
        );

        return response()->json(new InstallmentResource($installment));
    }

    public function updateDueDate(
        UpdateInstallmentDueDateRequest $request,
        string|int $id,
        UpdateInstallmentDueDateAction $action,
    ): JsonResponse {
        $installment = Installment::query()->findOrFail((int) $id);

        if ($installment->status === Installment::STATUS_PAID) {
            return response()->json(['error' => 'Não é possível alterar a data de vencimento de uma parcela já paga.'], 422);
        }

        $installment = $action->execute($installment, (string) $request->validated('due_date'));

        return response()->json(new InstallmentResource($installment));
    }

    public function recibo(string|int $id, GenerateInstallmentReciboPdfAction $action): Response
    {
        $this->authorize('sales.view');

        $installment = Installment::query()->findOrFail((int) $id);

        if ($installment->status !== Installment::STATUS_PAID) {
            abort(422, 'Esta parcela ainda não foi paga — não é possível emitir o recibo.');
        }

        $pdfBytes = $action->execute($installment);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$action->filename($installment).'"',
        ]);
    }

    public function sendReciboWhatsApp(string|int $id, SendInstallmentReciboWhatsappAction $action): JsonResponse
    {
        $this->authorize('sales.edit');

        $installment = Installment::query()
            ->with(['sale.client'])
            ->findOrFail((int) $id);

        if ($installment->status !== Installment::STATUS_PAID) {
            return response()->json(['error' => 'Esta parcela ainda não foi paga — não é possível enviar o recibo.'], 422);
        }

        $client = $installment->sale?->client;
        $phone = trim((string) ($client?->phone ?? ''));

        if ($phone === '') {
            return response()->json([
                'error' => 'Cliente sem telefone/WhatsApp cadastrado.',
            ], 422);
        }

        $result = $action->execute($installment, $phone);

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
}
