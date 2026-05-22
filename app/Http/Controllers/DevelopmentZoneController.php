<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Development;
use App\Models\DevelopmentZone;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevelopmentZoneController extends Controller
{
    public function index(string|int $developmentId): JsonResponse
    {
        $this->authorize('developments.view');

        $development = Development::query()->with('zones.lots')->findOrFail((int) $developmentId);

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
}
