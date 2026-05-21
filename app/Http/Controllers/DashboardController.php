<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Development;
use App\Models\Installment;
use App\Models\Lot;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now();

        $lotsByStatus = Lot::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalSales = Sale::query()->count();
        $activeSales = Sale::query()->where('status', Sale::STATUS_ACTIVE)->count();
        $totalRevenue = Sale::query()->where('status', '!=', Sale::STATUS_CANCELLED)->sum('total_value');
        $totalReceived = Installment::query()->where('status', Installment::STATUS_PAID)->sum('value');
        $totalPending = Installment::query()->where('status', Installment::STATUS_PENDING)->sum('value');

        $monthInstallments = Installment::query()
            ->whereMonth('due_date', $thisMonth->month)
            ->whereYear('due_date', $thisMonth->year)
            ->selectRaw('status, count(*) as count, sum(value) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $monthOverdueCount = Installment::query()
            ->whereMonth('due_date', $thisMonth->month)
            ->whereYear('due_date', $thisMonth->year)
            ->where('status', Installment::STATUS_PENDING)
            ->where('due_date', '<', $today)
            ->count();

        $monthOverdueTotal = Installment::query()
            ->whereMonth('due_date', $thisMonth->month)
            ->whereYear('due_date', $thisMonth->year)
            ->where('status', Installment::STATUS_PENDING)
            ->where('due_date', '<', $today)
            ->sum('value');

        $overdueInstallments = Installment::query()
            ->where('status', Installment::STATUS_PENDING)
            ->where('due_date', '<', $today)
            ->with(['sale.client', 'sale.lot.development'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $upcomingInstallments = Installment::query()
            ->where('status', Installment::STATUS_PENDING)
            ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
            ->with(['sale.client', 'sale.lot.development'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $totalClients = Client::query()->count();
        $clientsWhatsapp = Client::query()->where('whatsapp_status', 'confirmed')->count();

        return response()->json([
            'total_developments' => Development::query()->count(),
            'total_lots' => Lot::query()->count(),
            'lots_by_status' => [
                'available' => (int) ($lotsByStatus['available'] ?? 0),
                'reserved' => (int) ($lotsByStatus['reserved'] ?? 0),
                'sold' => (int) ($lotsByStatus['sold'] ?? 0),
            ],
            'total_sales' => $totalSales,
            'active_sales' => $activeSales,
            'total_revenue' => (int) $totalRevenue,
            'total_received' => (int) $totalReceived,
            'total_pending' => (int) $totalPending,
            'month_installments' => [
                'paid' => [
                    'count' => (int) ($monthInstallments->get('paid')?->count ?? 0),
                    'total' => (int) ($monthInstallments->get('paid')?->total ?? 0),
                ],
                'pending' => [
                    'count' => (int) ($monthInstallments->get('pending')?->count ?? 0),
                    'total' => (int) ($monthInstallments->get('pending')?->total ?? 0),
                ],
                'overdue' => [
                    'count' => (int) $monthOverdueCount,
                    'total' => (int) $monthOverdueTotal,
                ],
            ],
            'overdue_installments' => $overdueInstallments->map(fn (Installment $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'due_date' => $i->due_date?->toDateString(),
                'value' => (int) $i->value,
                'client' => $i->sale?->client?->name ?? '–',
                'lote' => 'Q' . ($i->sale?->lot?->block ?? '?') . ' L' . ($i->sale?->lot?->number ?? '?'),
                'type' => $i->type,
                'label' => $i->type === Installment::TYPE_DOWN_PAYMENT ? 'Entrada' : 'Parcela ' . $i->number,
                'sale_id' => $i->sale_id,
            ]),
            'upcoming_installments' => $upcomingInstallments->map(fn (Installment $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'due_date' => $i->due_date?->toDateString(),
                'value' => (int) $i->value,
                'client' => $i->sale?->client?->name ?? '–',
                'lote' => 'Q' . ($i->sale?->lot?->block ?? '?') . ' L' . ($i->sale?->lot?->number ?? '?'),
                'type' => $i->type,
                'label' => $i->type === Installment::TYPE_DOWN_PAYMENT ? 'Entrada' : 'Parcela ' . $i->number,
                'sale_id' => $i->sale_id,
            ]),
            'total_clients' => $totalClients,
            'clients_whatsapp' => $clientsWhatsapp,
            'recent_developments' => Development::query()
                ->withCount('lots')
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'location', 'status', 'created_at']),
        ]);
    }
}
