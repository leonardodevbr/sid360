<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Installment\PayInstallmentAction;
use App\Http\Resources\InstallmentResource;
use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function pay(Request $request, string|int $id, PayInstallmentAction $action): JsonResponse
    {
        $this->authorize('sales.edit');

        $installment = Installment::query()->findOrFail((int) $id);
        $installment = $action->execute($installment, $request->input('paid_at'));

        return response()->json(new InstallmentResource($installment));
    }
}
