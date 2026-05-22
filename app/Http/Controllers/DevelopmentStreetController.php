<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Development;
use App\Models\DevelopmentStreet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevelopmentStreetController extends Controller
{
    public function index(string|int $developmentId): JsonResponse
    {
        $this->authorize('developments.view');

        $streets = DevelopmentStreet::query()
            ->where('development_id', $developmentId)
            ->orderBy('order')
            ->get();

        return response()->json($streets);
    }

    public function store(Request $request, string|int $developmentId): JsonResponse
    {
        $this->authorize('developments.edit');

        $development = Development::query()->findOrFail((int) $developmentId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'coordinates' => ['nullable', 'array'],
            'order' => ['nullable', 'integer'],
        ]);

        $street = $development->streets()->create($data);

        return response()->json($street, 201);
    }

    public function update(Request $request, string|int $developmentId, string|int $streetId): JsonResponse
    {
        $this->authorize('developments.edit');

        $street = DevelopmentStreet::query()
            ->where('development_id', $developmentId)
            ->findOrFail((int) $streetId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'coordinates' => ['nullable', 'array'],
            'order' => ['nullable', 'integer'],
        ]);

        $street->update($data);

        return response()->json($street);
    }

    public function destroy(string|int $developmentId, string|int $streetId): JsonResponse
    {
        $this->authorize('developments.edit');

        $street = DevelopmentStreet::query()
            ->where('development_id', $developmentId)
            ->findOrFail((int) $streetId);

        $street->delete();

        return response()->json(['message' => 'Rua excluída.']);
    }
}
