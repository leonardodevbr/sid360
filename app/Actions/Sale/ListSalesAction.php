<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Installment;
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
                'financingInstallments as paid_installments_count' => fn ($q) => $q->where(
                    'status',
                    Installment::STATUS_PAID,
                ),
            ])
            ->withMax('installments as latest_whatsapp_reminder_at', 'whatsapp_reminder_sent_at')
            ->withMax('installments as latest_whatsapp_overdue_at', 'whatsapp_overdue_sent_at')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('sale_date')
            ->paginate(15);
    }
}
