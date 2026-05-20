<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_id' => $this->lot_id,
            'client_id' => $this->client_id,
            'lot' => $this->whenLoaded('lot', fn () => new LotResource($this->lot)),
            'client' => $this->whenLoaded('client', fn () => new ClientResource($this->client)),
            'installments' => $this->whenLoaded('installments', fn () => InstallmentResource::collection($this->installments)),
            'sale_date' => $this->sale_date?->toDateString(),
            'total_value' => (int) $this->total_value,
            'cash_value' => $this->cash_value !== null ? (int) $this->cash_value : null,
            'down_payment' => (int) $this->down_payment,
            'financed_value' => (int) $this->financed_value,
            'installments_count' => $this->installments_count,
            'installment_value' => (int) $this->installment_value,
            'first_due_date' => $this->first_due_date?->toDateString(),
            'payment_day' => $this->payment_day,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
