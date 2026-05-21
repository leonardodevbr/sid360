<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ListSalesAction
{
    /**
     * @return LengthAwarePaginator<Sale>
     */
    public function execute(Request $request): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['client', 'lot.development'])
            ->withCount([
                'installments as overdue_installments_count' => fn ($q) => $q->overdue(),
            ])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('sale_date')
            ->paginate(15);
    }
}
