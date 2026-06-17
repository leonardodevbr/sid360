<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Development;
use App\Models\DevelopmentZone;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DevelopmentZoneController extends Controller
{
    public function index(string|int $developmentId): JsonResponse
    {
        $this->authorize('developments.view');

        $development = Development::query()->with(['zones.lots', 'zones.parent'])->findOrFail((int) $developmentId);

        return response()->json($development->zones);
    }

    public function store(Request $request, string|int $developmentId): JsonResponse
    {
        $this->authorize('developments.edit');

        $development = Development::query()->findOrFail((int) $developmentId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:' . implode(',', DevelopmentZone::TYPES)],
            'color' => ['nullable', 'string', 'max:10'],
            'coordinates' => ['nullable', 'array'],
            'order' => ['nullable', 'integer'],
            'parent_zone_id' => [
                'nullable',
                'integer',
                Rule::exists('development_zones', 'id')->where(
                    fn ($query) => $query->where('development_id', $development->id),
                ),
            ],
        ]);

        $zone = $development->zones()->create($data);

        return response()->json($zone, 201);
    }

    public function update(Request $request, string|int $developmentId, string|int $zoneId): JsonResponse
    {
        $this->authorize('developments.edit');

        $zone = DevelopmentZone::query()
            ->where('development_id', $developmentId)
            ->findOrFail((int) $zoneId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'string', 'in:' . implode(',', DevelopmentZone::TYPES)],
            'color' => ['nullable', 'string', 'max:10'],
            'coordinates' => ['nullable', 'array'],
            'order' => ['nullable', 'integer'],
            'parent_zone_id' => [
                'nullable',
                'integer',
                Rule::exists('development_zones', 'id')->where(
                    fn ($query) => $query->where('development_id', $zone->development_id),
                ),
            ],
        ]);

        $zone->update($data);

        return response()->json($zone);
    }

    public function destroy(string|int $developmentId, string|int $zoneId): JsonResponse
    {
        $this->authorize('developments.edit');

        $zone = DevelopmentZone::query()
            ->where('development_id', $developmentId)
            ->findOrFail((int) $zoneId);

        $zone->delete();

        return response()->json(['message' => 'Zona excluída.']);
    }

    public function generateLots(Request $request, string|int $developmentId, string|int $zoneId): JsonResponse
    {
        $this->authorize('lots.create');

        $development = Development::query()->findOrFail((int) $developmentId);
        $zone = DevelopmentZone::query()
            ->where('development_id', $developmentId)
            ->findOrFail((int) $zoneId);

        if (! $zone->allowsLotGeneration()) {
            $message = ! in_array($zone->type, DevelopmentZone::LOT_GENERATION_TYPES, true)
                ? 'Este tipo de zona não permite geração automática de lotes.'
                : 'Defina a área da zona no mapa antes de gerar lotes.';

            return response()->json([
                'message' => $message,
                'errors' => ['zone' => [$message]],
            ], 422);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'start_from' => ['nullable', 'integer', 'min:1'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'total_value' => ['nullable', 'integer', 'min:0'],
            'pattern' => ['nullable', 'string', 'max:100'],
        ]);

        $quantity = (int) $data['quantity'];
        $startFrom = (int) ($data['start_from'] ?? 1);
        $pattern = $data['pattern'] ?? $development->lot_number_pattern ?? '{zona}-L{numero2}';
        $created = [];

        $lastNumber = Lot::query()
            ->where('zone_id', $zone->id)
            ->max('number');

        $nextNumber = $lastNumber ? ((int) preg_replace('/\D/', '', $lastNumber) + 1) : $startFrom;

        for ($i = 0; $i < $quantity; $i++) {
            $num = $nextNumber + $i;

            $number = $pattern;
            $number = str_replace('{zona}', $zone->name, $number);
            $number = str_replace('{numero}', (string) $num, $number);
            $number = str_replace('{numero2}', str_pad((string) $num, 2, '0', STR_PAD_LEFT), $number);
            $number = str_replace('{numero3}', str_pad((string) $num, 3, '0', STR_PAD_LEFT), $number);

            $lot = Lot::query()->create([
                'development_id' => $development->id,
                'zone_id' => $zone->id,
                'block' => $zone->name,
                'number' => $number,
                'area' => $data['area'] ?? null,
                'total_value' => $data['total_value'] ?? null,
                'down_payment_percent' => null,
                'status' => Lot::STATUS_AVAILABLE,
            ]);

            $created[] = $lot;
        }

        return response()->json([
            'created' => count($created),
            'lots' => $created,
        ], 201);
    }

    public function generateLotsGeometric(Request $request, string|int $developmentId, string|int $zoneId): JsonResponse
    {
        $this->authorize('lots.create');

        $development = Development::query()->findOrFail((int) $developmentId);
        $zone = DevelopmentZone::query()
            ->where('development_id', $developmentId)
            ->findOrFail((int) $zoneId);

        if (! $zone->allowsLotGeneration()) {
            return response()->json([
                'message' => 'Defina a área da zona no mapa antes de gerar lotes.',
                'errors' => ['zone' => ['Área da zona não definida.']],
            ], 422);
        }

        $data = $request->validate([
            'start_from' => ['nullable', 'integer', 'min:1'],
            'total_value' => ['nullable', 'integer', 'min:0'],
            'pattern' => ['nullable', 'string', 'max:100'],
            'lot_width' => ['nullable', 'numeric', 'min:0'],
            'lot_depth' => ['nullable', 'numeric', 'min:0'],
            'lots' => ['required', 'array', 'min:1', 'max:500'],
            'lots.*.coordinates' => ['required', 'array', 'min:3'],
            'lots.*.coordinates.*' => ['array', 'size:2'],
            'lots.*.area_computed' => ['nullable', 'numeric', 'min:0'],
            'lots.*.width_meters' => ['nullable', 'numeric', 'min:0'],
            'lots.*.depth_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $startFrom = (int) ($data['start_from'] ?? 1);
        $pattern = $data['pattern'] ?? $development->lot_number_pattern ?? '{zona}-L{numero2}';

        $lastNumber = Lot::query()->where('zone_id', $zone->id)->max('number');
        $nextNumber = $lastNumber ? ((int) preg_replace('/\D/', '', $lastNumber) + 1) : $startFrom;

        $created = [];

        DB::transaction(function () use ($data, $development, $zone, $pattern, $nextNumber, &$created): void {
            foreach (array_values($data['lots']) as $i => $lotData) {
                $num = $nextNumber + $i;

                $number = str_replace(
                    ['{zona}', '{numero}', '{numero2}', '{numero3}'],
                    [
                        $zone->name,
                        (string) $num,
                        str_pad((string) $num, 2, '0', STR_PAD_LEFT),
                        str_pad((string) $num, 3, '0', STR_PAD_LEFT),
                    ],
                    $pattern,
                );

                $sizeLabel = $this->buildLotSizeLabel(
                    $lotData['width_meters'] ?? $data['lot_width'] ?? null,
                    $lotData['depth_meters'] ?? $data['lot_depth'] ?? null,
                );

                $created[] = Lot::query()->create([
                    'development_id' => $development->id,
                    'zone_id' => $zone->id,
                    'block' => $zone->name,
                    'number' => $number,
                    'area' => $lotData['area_computed'] ?? null,
                    'area_computed' => $lotData['area_computed'] ?? null,
                    'size_label' => $sizeLabel,
                    'total_value' => $data['total_value'] ?? null,
                    'down_payment_percent' => null,
                    'status' => Lot::STATUS_AVAILABLE,
                    'coordinates' => $lotData['coordinates'],
                ]);
            }
        });

        return response()->json([
            'created' => count($created),
            'lots' => $created,
        ], 201);
    }

    private function formatLotMeasurement(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) $value;

        if ($numeric <= 0 || ! is_finite($numeric)) {
            return null;
        }

        if (abs($numeric - round($numeric)) < 0.001) {
            return (string) (int) round($numeric);
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    private function buildLotSizeLabel(mixed $width, mixed $depth): ?string
    {
        $widthLabel = $this->formatLotMeasurement($width);
        $depthLabel = $this->formatLotMeasurement($depth);

        if ($widthLabel === null || $depthLabel === null) {
            return null;
        }

        return "{$widthLabel}×{$depthLabel}m";
    }
}
