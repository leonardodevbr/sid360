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
        $query = Lot::query()->with('development');

        if ($request->filled('development_id')) {
            $query->where('development_id', (int) $request->input('development_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('block', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $query->orderBy('development_id')->orderBy('block')->orderBy('number');

        if ($request->boolean('all')) {
            return $query->get();
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        return $query->paginate($perPage);
    }
}
