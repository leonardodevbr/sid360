<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sale\DeleteSaleAction;
use App\Actions\Sale\ListSalesAction;
use App\Actions\Sale\StoreSaleAction;
use App\Actions\Sale\UpdateSaleAction;
use App\Actions\Sale\UploadSignedContractAction;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UploadSignedContractRequest;
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

class SaleController extends Controller
{
    public function index(Request $request, ListSalesAction $action): AnonymousResourceCollection
    {
        $this->authorize('sales.view');

        return SaleResource::collection($action->execute($request));
    }

    public function store(StoreSaleRequest $request, StoreSaleAction $action): JsonResponse
    {
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

        $sale->load(['client', 'lot.development', 'installments', 'buyers']);

        return response()->json(new SaleResource($sale), 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()
            ->with(['client', 'lot.development', 'installments', 'buyers'])
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

    public function contract(string|int $id): Response
    {
        $this->authorize('sales.view');

        $sale = Sale::query()
            ->with(['client', 'lot.development', 'buyers'])
            ->findOrFail((int) $id);

        $pdf = Pdf::loadView('pdf.contract', ['sale' => $sale])
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

        return $pdf->download("contrato-venda-{$sale->id}.pdf");
    }

    public function carne(string|int $id): Response
    {
        $this->authorize('sales.view');

        $sale = $this->findSaleForCarne($id);

        $pdf = Pdf::loadView('pdf.carne', ['sale' => $sale])
            ->setPaper('a4', 'portrait');

        return $pdf->download("carne-venda-{$sale->id}.pdf");
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
                'buyers',
                'financingInstallments',
            ])
            ->findOrFail((int) $id);

        if ($sale->installments_count < 1 || $sale->financingInstallments->isEmpty()) {
            abort(404, 'Esta venda não possui parcelas para carnê.');
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
        $sale->load(['client', 'lot.development', 'installments']);

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
}
