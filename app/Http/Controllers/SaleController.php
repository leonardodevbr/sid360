<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sale\CancelSaleAction;
use App\Actions\Sale\DeleteSaleAction;
use App\Actions\Sale\GenerateSaleContractPdfAction;
use App\Actions\Sale\ListSaleDocumentsAction;
use App\Actions\Sale\ListSalesAction;
use App\Actions\Sale\SendOverdueWhatsappAction;
use App\Actions\Sale\SnapshotSaleDocumentsAction;
use App\Actions\Sale\StoreSaleAction;
use App\Actions\Sale\UpdateSaleAction;
use App\Actions\Sale\UploadSaleDocumentAction;
use App\Actions\Sale\UploadSignedContractAction;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UploadSaleDocumentRequest;
use App\Http\Requests\UploadSignedContractRequest;
use App\Http\Resources\SaleDocumentResource;
use App\Http\Resources\SaleResource;
use App\Models\Installment;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function index(Request $request, ListSalesAction $action): AnonymousResourceCollection
    {
        $this->authorize('sales.view');

        return SaleResource::collection($action->execute($request));
    }

    public function store(
        StoreSaleRequest $request,
        StoreSaleAction $action,
        SnapshotSaleDocumentsAction $snapshotDocumentsAction
    ): JsonResponse {
        $this->authorize('sales.create');

        $validated = $request->validated();
        $coBuyerIds = $validated['co_buyer_ids'] ?? [];
        unset($validated['co_buyer_ids']);

        $sale = $action->execute($validated);

        foreach ($coBuyerIds as $i => $clientId) {
            $sale->buyers()->syncWithoutDetaching([
                (int) $clientId => ['role' => 'co_buyer', 'order' => $i + 1],
            ]);
        }

        // Congela, na pasta do empreendimento, os documentos atuais do cliente
        // no momento da venda (perfil geral do cliente continua evoluindo livre).
        $snapshotDocumentsAction->execute($sale);

        $sale->load(['client', 'lot.development', 'lots.development', 'installments', 'buyers']);

        return response()->json(new SaleResource($sale), 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()
            ->with([
                'client',
                'lot.development',
                'lot.street',
                'lot.zone.parent',
                'lots.development',
                'lots.street',
                'lots.zone.parent',
                'installments',
                'buyers',
            ])
            ->findOrFail((int) $id);

        return response()->json(new SaleResource($sale));
    }

    public function update(Request $request, string|int $id, UpdateSaleAction $action): JsonResponse
    {
        $this->authorize('sales.edit');

        $sale = Sale::query()->findOrFail((int) $id);
        $sale = $action->execute($sale, $request->only(['status', 'notes']));

        return response()->json(new SaleResource($sale));
    }

    public function destroy(string|int $id, DeleteSaleAction $action): JsonResponse
    {
        $this->authorize('sales.delete');

        $sale = Sale::query()->findOrFail((int) $id);
        $action->execute($sale);

        return response()->json(['message' => 'Venda excluída com sucesso.']);
    }

    public function cancel(CancelSaleRequest $request, string|int $id, CancelSaleAction $action): JsonResponse
    {
        $this->authorize('sales.cancel');

        $sale = Sale::query()->findOrFail((int) $id);

        try {
            $sale = $action->execute($sale, (string) $request->validated('reason'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sale->load(['client', 'lot.development', 'lots.development', 'installments', 'buyers']);

        return response()->json(new SaleResource($sale));
    }

    public function sendOverdueWhatsapp(string|int $id, SendOverdueWhatsappAction $action): JsonResponse
    {
        $this->authorize('sales.edit');

        Sale::query()->findOrFail((int) $id);

        $result = $action->execute(
            saleId: (int) $id,
            forceResend: true,
            sendEmail: false,
        );

        if (! $result['ok']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function contract(string|int $id, GenerateSaleContractPdfAction $action): Response
    {
        $this->authorize('sales.view');

        $sale = Sale::query()
            ->with([
                'client',
                'lot.development',
                'lot.street',
                'lot.zone.parent',
                'lots.street',
                'lots.zone.parent',
                'buyers',
            ])
            ->findOrFail((int) $id);

        $pdf = $action->execute($sale);
        $filename = $action->filename($sale);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function contractPreview(string|int $id, GenerateSaleContractPdfAction $action): Response
    {
        $this->authorize('sales.view');

        $sale = Sale::query()
            ->with([
                'client',
                'lot.development',
                'lot.street',
                'lot.zone.parent',
                'lots.street',
                'lots.zone.parent',
                'buyers',
            ])
            ->findOrFail((int) $id);

        $pdf = $action->execute($sale, isDraft: true);
        $filename = $action->filename($sale, isDraft: true);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function carne(string|int $id): Response
    {
        $this->authorize('sales.view');

        $sale = $this->findSaleForCarne($id);

        $pdf = Pdf::loadView('pdf.carne', ['sale' => $sale])
            ->setPaper('a4', 'portrait');

        return $pdf->download("promissoria-venda-{$sale->id}.pdf");
    }

    public function carnePreviewHtml(string|int $id): Response
    {
        if (! app()->isLocal()) {
            abort(404);
        }

        $this->authorize('sales.view');

        $sale = $this->findSaleForCarne($id);

        return response()
            ->view('pdf.carne', ['sale' => $sale, 'isPreview' => true])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function carnePreview(string|int $id): View
    {
        if (! app()->isLocal()) {
            abort(404);
        }

        $sale = $this->findSaleForCarne($id);

        return view('pdf.carne', ['sale' => $sale, 'isPreview' => true]);
    }

    private function findSaleForCarne(string|int $id): Sale
    {
        $sale = Sale::query()
            ->with([
                'client',
                'lot.development',
                'lots',
                'buyers',
                'financingInstallments',
            ])
            ->findOrFail((int) $id);

        if ($sale->installments_count < 1 || $sale->financingInstallments->isEmpty()) {
            abort(404, 'Esta venda não possui parcelas para promissória.');
        }

        $sale->setRelation('installments', $sale->financingInstallments);

        return $sale;
    }

    public function uploadSignedContract(
        UploadSignedContractRequest $request,
        string|int $id,
        UploadSignedContractAction $action,
    ): JsonResponse {
        $this->authorize('sales.edit');

        $sale = Sale::query()->findOrFail((int) $id);
        $sale = $action->execute($sale, $request->file('file'));
        $sale->load(['client', 'lot.development', 'lots.development', 'installments']);

        return response()->json(new SaleResource($sale));
    }

    public function signedContract(string|int $id): BinaryFileResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->findOrFail((int) $id);

        if ($sale->signed_contract_path === null || ! Storage::disk('local')->exists($sale->signed_contract_path)) {
            abort(404, 'Contrato assinado não encontrado.');
        }

        return Storage::disk('local')->download(
            $sale->signed_contract_path,
            $sale->signed_contract_original_name ?? "contrato-assinado-venda-{$sale->id}.pdf",
        );
    }

    public function documents(string|int $id, ListSaleDocumentsAction $action): AnonymousResourceCollection
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->findOrFail((int) $id);

        return SaleDocumentResource::collection($action->execute($sale));
    }

    public function uploadDocument(
        UploadSaleDocumentRequest $request,
        string|int $id,
        UploadSaleDocumentAction $action
    ): JsonResponse {
        $this->authorize('sales.edit');

        $sale = Sale::query()->findOrFail((int) $id);

        $document = $action->execute(
            $sale,
            $request->file('file'),
            (string) $request->validated('type'),
            $request->user()?->id,
            (string) ($request->validated('side') ?? \App\Models\ClientDocument::SIDE_ABERTO),
        );

        return response()->json(new SaleDocumentResource($document), 201);
    }

    public function downloadDocument(string|int $id, string|int $documentId): StreamedResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->findOrFail((int) $id);
        $document = $sale->documents()->findOrFail((int) $documentId);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function previewDocument(string|int $id, string|int $documentId): Response
    {
        $this->authorize('sales.view');

        $sale = Sale::query()->findOrFail((int) $id);
        $document = $sale->documents()->findOrFail((int) $documentId);

        $contents = Storage::disk($document->disk)->get($document->path);

        return response($contents, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
        ]);
    }
}
