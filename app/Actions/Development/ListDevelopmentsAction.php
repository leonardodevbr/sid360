<?php

declare(strict_types=1);

namespace App\Actions\Development;

use App\Models\Development;
use App\Models\Lot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ListDevelopmentsAction
{
    /**
     * @return LengthAwarePaginator<Development>|Collection<int, Development>
     */
    public function execute(Request $request): LengthAwarePaginator|Collection
    {
        $query = Development::query()->withCount([
            'lots',
            'lots as available_lots_count' => static function ($q): void {
                $q->where('status', Lot::STATUS_AVAILABLE);
            },
        ]);

        if ($request->boolean('has_available_lots')) {
            $query->whereHas('lots', static function ($q): void {
                $q->where('status', Lot::STATUS_AVAILABLE);
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $query->orderBy('name');

        if ($request->boolean('all')) {
            return $query->get();
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        return $query->paginate($perPage);
    }
}
