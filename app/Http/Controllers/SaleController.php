<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sale\DeleteSaleAction;
use App\Actions\Sale\ListSalesAction;
use App\Actions\Sale\StoreSaleAction;
use App\Actions\Sale\UpdateSaleAction;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

        $sale = $action->execute($request->validated());
        $sale->load(['client', 'lot.development', 'installments']);

        return response()->json(new SaleResource($sale), 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $sale = Sale::query()
            ->with(['client', 'lot.development', 'installments'])
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
            ->with(['client', 'lot.development'])
            ->findOrFail((int) $id);

        $pdf = Pdf::loadView('pdf.contract', ['sale' => $sale])
            ->setPaper('a4', 'portrait');

        return $pdf->download("contrato-venda-{$sale->id}.pdf");
    }
}
