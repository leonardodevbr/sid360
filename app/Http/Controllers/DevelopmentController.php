<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Development\DeleteDevelopmentAction;
use App\Actions\Development\ListDevelopmentsAction;
use App\Actions\Development\StoreDevelopmentAction;
use App\Actions\Development\UpdateDevelopmentAction;
use App\Actions\Lot\ListLotsAction;
use App\Http\Requests\ExportTechnicalMapPdfRequest;
use App\Http\Requests\StoreDevelopmentRequest;
use App\Http\Requests\UpdateDevelopmentRequest;
use App\Http\Resources\DevelopmentResource;
use App\Http\Resources\LotResource;
use App\Models\Development;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DevelopmentController extends Controller
{
    public function index(Request $request, ListDevelopmentsAction $action): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('developments.view');

        $result = $action->execute($request);

        if ($request->boolean('all')) {
            return response()->json(DevelopmentResource::collection($result));
        }

        return DevelopmentResource::collection($result);
    }

    public function store(StoreDevelopmentRequest $request, StoreDevelopmentAction $action): JsonResponse
    {
        $this->authorize('developments.create');

        $development = $action->execute($request->validated());

        return response()->json(new DevelopmentResource($development), 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $this->authorize('developments.view');

        $development = Development::query()
            ->with(['zones.lots'])
            ->withCount('lots')
            ->findOrFail((int) $id);

        return response()->json(new DevelopmentResource($development));
    }

    public function update(
        UpdateDevelopmentRequest $request,
        string|int $id,
        UpdateDevelopmentAction $action
    ): JsonResponse {
        $this->authorize('developments.edit');

        $development = Development::query()->findOrFail((int) $id);
        $development = $action->execute($development, $request->validated());

        return response()->json(new DevelopmentResource($development));
    }

    public function destroy(string|int $id, DeleteDevelopmentAction $action): JsonResponse
    {
        $this->authorize('developments.delete');

        $development = Development::query()->findOrFail((int) $id);
        $action->execute($development);

        return response()->json(['message' => 'Empreendimento excluído com sucesso.']);
    }

    public function lots(Request $request, string|int $id, ListLotsAction $action): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('lots.view');

        Development::query()->findOrFail((int) $id);

        $request->merge(['development_id' => (int) $id]);

        $result = $action->execute($request);

        if ($request->boolean('all')) {
            return response()->json(LotResource::collection($result));
        }

        return LotResource::collection($result);
    }

    public function technicalMapPdf(
        ExportTechnicalMapPdfRequest $request,
        string|int $id,
    ): Response {
        $this->authorize('developments.view');

        Development::query()->findOrFail((int) $id);

        $paperSize = strtolower((string) $request->validated('paper_size', 'a3'));
        $orientation = (string) $request->validated('orientation', 'landscape');

        $pdf = Pdf::loadView('pdf.technical-map', [
            'svg' => $request->validated('svg'),
        ])->setPaper($paperSize, $orientation);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="planta-tecnica-'.$id.'.pdf"',
        ]);
    }
}
