<?php

declare(strict_types=1);

namespace App\Actions\Lot;

use App\Models\Lot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ListLotsAction
{
    /**
     * @return LengthAwarePaginator<Lot>|Collection<int, Lot>
     */
    public function execute(Request $request): LengthAwarePaginator|Collection
    {
        $query = Lot::query()->with(['development', 'zone.parent', 'street']);

        if ($request->filled('development_id')) {
            $query->where('lots.development_id', (int) $request->input('development_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('lots.number', 'like', "%{$search}%")
                    ->orWhere('lots.block', 'like', "%{$search}%")
                    ->orWhereHas('zone', function ($zoneQuery) use ($search): void {
                        $zoneQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('lots.status', $request->string('status')->toString());
        }

        if ($request->filled('zone_ids')) {
            $zoneIds = collect((array) $request->input('zone_ids'))
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($zoneIds !== []) {
                $query->whereIn('lots.zone_id', $zoneIds);
            }
        }

        if ($request->filled('block')) {
            $block = $request->string('block')->toString();
            $query->where('lots.block', 'like', "%{$block}%");
        }

        if ($request->filled('size_label')) {
            $sizeLabel = $request->string('size_label')->toString();
            $query->where('lots.size_label', 'like', "%{$sizeLabel}%");
        }

        if ($request->filled('area_min')) {
            $query->whereRaw('COALESCE(lots.area, lots.area_computed) >= ?', [
                (float) $request->input('area_min'),
            ]);
        }

        if ($request->filled('area_max')) {
            $query->whereRaw('COALESCE(lots.area, lots.area_computed) <= ?', [
                (float) $request->input('area_max'),
            ]);
        }

        if ($request->filled('value_min')) {
            $query->where('lots.total_value', '>=', (int) $request->input('value_min'));
        }

        if ($request->filled('value_max')) {
            $query->where('lots.total_value', '<=', (int) $request->input('value_max'));
        }

        $query
            ->leftJoin('development_zones as lot_zones', 'lots.zone_id', '=', 'lot_zones.id')
            ->orderBy('lots.development_id')
            ->orderBy('lot_zones.name')
            ->orderBy('lots.block')
            ->orderBy('lots.number')
            ->select('lots.*');

        if ($request->boolean('all')) {
            return $query->get();
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        return $query->paginate($perPage);
    }
}
