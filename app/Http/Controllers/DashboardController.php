<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Development;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $lotsByStatus = Lot::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'total_developments' => Development::query()->count(),
            'total_lots' => Lot::query()->count(),
            'lots_by_status' => [
                'available' => (int) ($lotsByStatus['available'] ?? 0),
                'reserved' => (int) ($lotsByStatus['reserved'] ?? 0),
                'sold' => (int) ($lotsByStatus['sold'] ?? 0),
            ],
            'recent_developments' => Development::query()
                ->withCount('lots')
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'location', 'status', 'created_at']),
        ]);
    }
}
