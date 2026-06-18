<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Lot\BulkDeleteLotsAction;
use App\Actions\Lot\BulkUpdateLotsAction;
use App\Actions\Lot\BulkUpdateLotsStatusAction;
use App\Actions\Lot\DeleteLotAction;
use App\Actions\Lot\ListLotsAction;
use App\Actions\Lot\StoreLotAction;
use App\Actions\Lot\UpdateLotAction;
use App\Http\Requests\BulkDeleteLotsRequest;
use App\Http\Requests\BulkUpdateLotsRequest;
use App\Http\Requests\BulkUpdateLotsStatusRequest;
use App\Http\Requests\StoreLotRequest;
use App\Http\Requests\UpdateLotRequest;
use App\Http\Resources\LotResource;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LotController extends Controller
{
    public function index(Request $request, ListLotsAction $action): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('lots.view');

        $result = $action->execute($request);

        if ($request->boolean('all')) {
            return response()->json(LotResource::collection($result));
        }

        return LotResource::collection($result);
    }

    public function store(StoreLotRequest $request, StoreLotAction $action): JsonResponse
    {
        $this->authorize('lots.create');

        $lot = $action->execute($request->validated());
        $lot->load('development');

        return response()->json(new LotResource($lot), 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $this->authorize('lots.view');

        $lot = Lot::query()->with(['development', 'zone.parent', 'street'])->findOrFail((int) $id);

        return response()->json(new LotResource($lot));
    }

    public function update(UpdateLotRequest $request, string|int $id, UpdateLotAction $action): JsonResponse
    {
        $this->authorize('lots.edit');

        $lot = Lot::query()->findOrFail((int) $id);
        $lot = $action->execute($lot, $request->validated());

        return response()->json(new LotResource($lot));
    }

    public function destroy(string|int $id, DeleteLotAction $action): JsonResponse
    {
        $this->authorize('lots.delete');

        $lot = Lot::query()->findOrFail((int) $id);
        $action->execute($lot);

        return response()->json(['message' => 'Lote excluído com sucesso.']);
    }

    public function bulkDestroy(BulkDeleteLotsRequest $request, BulkDeleteLotsAction $action): JsonResponse
    {
        $this->authorize('lots.delete');

        $result = $action->execute($request->validated('ids'));

        return response()->json([
            'message' => $result['deleted'] > 0
                ? "{$result['deleted']} lote(s) excluído(s)."
                : 'Nenhum lote foi excluído.',
            ...$result,
        ]);
    }

    public function bulkUpdateStatus(BulkUpdateLotsStatusRequest $request, BulkUpdateLotsStatusAction $action): JsonResponse
    {
        $this->authorize('lots.edit');

        $result = $action->execute(
            $request->validated('ids'),
            $request->validated('status'),
        );

        return response()->json([
            'message' => $result['updated'] > 0
                ? "{$result['updated']} lote(s) atualizado(s)."
                : 'Nenhum lote foi atualizado.',
            ...$result,
        ]);
    }

    public function bulkUpdate(BulkUpdateLotsRequest $request, BulkUpdateLotsAction $action): JsonResponse
    {
        $this->authorize('lots.edit');

        $validated = $request->validated();
        $ids = $validated['ids'];
        unset($validated['ids']);

        $result = $action->execute($ids, $validated);

        return response()->json([
            'message' => $result['updated'] > 0
                ? "{$result['updated']} lote(s) atualizado(s)."
                : 'Nenhum lote foi atualizado.',
            ...$result,
        ]);
    }
}
