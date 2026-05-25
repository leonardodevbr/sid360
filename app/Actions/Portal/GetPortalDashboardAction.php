<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Models\Client;
use App\Models\Installment;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class GetPortalDashboardAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $clientId): array
    {
        $client = Client::query()->findOrFail($clientId);
        $today = Carbon::today();

        $sales = Sale::query()
            ->where('status', '!=', Sale::STATUS_CANCELLED)
            ->where(function (Builder $query) use ($clientId): void {
                $query->where('client_id', $clientId)
                    ->orWhereHas('buyers', fn (Builder $buyerQuery) => $buyerQuery->where('clients.id', $clientId));
            })
            ->with(['lot.development', 'installments'])
            ->orderByDesc('sale_date')
            ->get();

        return [
            'client' => [
                'name' => $client->name,
            ],
            'whatsapp_number' => config('portal.whatsapp_number'),
            'sales' => $sales->map(function (Sale $sale) use ($today): array {
                $installments = $sale->installments->map(function (Installment $installment) use ($today, $sale): array {
                    $status = $installment->status;

                    if ($status === Installment::STATUS_PENDING && $installment->due_date?->lt($today)) {
                        $status = Installment::STATUS_OVERDUE;
                    }

                    return [
                        'id' => $installment->id,
                        'type' => $installment->type,
                        'number' => $installment->number,
                        'due_date' => $installment->due_date?->toDateString(),
                        'value' => (int) $installment->value,
                        'paid_at' => $installment->paid_at?->toDateString(),
                        'status' => $status,
                        'sale_id' => $sale->id,
                        'contract_no' => $this->contractNumber($sale),
                        'efi_charge_id' => $installment->efi_charge_id,
                        'efi_txid' => $installment->efi_txid,
                        'efi_barcode' => $installment->efi_barcode,
                        'efi_pdf_url' => $installment->efi_pdf_url,
                        'efi_pix_copia_cola' => $installment->efi_pix_copia_cola,
                        'efi_pix_qrcode' => $installment->efi_pix_qrcode,
                        'efi_payment_type' => $installment->efi_payment_type,
                    ];
                })->values();

                $pending = $installments->whereIn('status', [Installment::STATUS_PENDING, Installment::STATUS_OVERDUE]);

                return [
                    'id' => $sale->id,
                    'contract_no' => $this->contractNumber($sale),
                    'sale_date' => $sale->sale_date?->toDateString(),
                    'status' => $sale->status,
                    'total_value' => (int) $sale->total_value,
                    'down_payment' => (int) $sale->down_payment,
                    'installments_count' => $sale->installments_count,
                    'lot' => [
                        'block' => $sale->lot?->block,
                        'number' => $sale->lot?->number,
                    ],
                    'development' => [
                        'name' => $sale->lot?->development?->name,
                    ],
                    'summary' => [
                        'paid_count' => $installments->where('status', Installment::STATUS_PAID)->count(),
                        'pending_count' => $pending->count(),
                        'overdue_count' => $installments->where('status', Installment::STATUS_OVERDUE)->count(),
                        'pending_value' => (int) $pending->sum('value'),
                    ],
                    'installments' => $installments,
                ];
            })->values(),
        ];
    }

    private function contractNumber(Sale $sale): string
    {
        return str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y');
    }
}
