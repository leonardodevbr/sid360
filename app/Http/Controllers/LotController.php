<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Lot\DeleteLotAction;
use App\Actions\Lot\ListLotsAction;
use App\Actions\Lot\StoreLotAction;
use App\Actions\Lot\UpdateLotAction;
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

        $lot = Lot::query()->with('development')->findOrFail((int) $id);

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

        return response()->json(['message' => 'Lot deleted successfully.']);
    }
}
