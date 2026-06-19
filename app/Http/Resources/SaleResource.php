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
            'buyers' => $this->whenLoaded('buyers', fn () => ClientResource::collection($this->buyers)),
            'installments' => $this->whenLoaded('installments', fn () => InstallmentResource::collection($this->installments)),
            'sale_date' => $this->sale_date?->toDateString(),
            'total_value' => (int) $this->total_value,
            'cash_value' => $this->cash_value !== null ? (int) $this->cash_value : null,
            'discount_amount' => (int) $this->discount_amount,
            'discount_percent' => $this->discount_percent !== null ? (float) $this->discount_percent : null,
            'down_payment' => (int) $this->down_payment,
            'financed_value' => (int) $this->financed_value,
            'installments_count' => $this->installments_count,
            'installment_value' => (int) $this->installment_value,
            'first_due_date' => $this->first_due_date?->toDateString(),
            'payment_day' => $this->payment_day,
            'status' => $this->status,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'efi_carnet_id' => $this->efi_carnet_id,
            'efi_carnet_pdf' => $this->efi_carnet_pdf,
            'efi_carnet_link' => $this->efi_carnet_link,
            'overdue_installments_count' => (int) ($this->overdue_installments_count ?? 0),
            'has_overdue_installments' => (int) ($this->overdue_installments_count ?? 0) > 0,
            'paid_installments_count' => (int) ($this->paid_installments_count ?? 0),
            'whatsapp_welcome_sent_at' => $this->whatsapp_welcome_sent_at?->toIso8601String(),
            'whatsapp_last_notification_at' => $this->lastWhatsappNotificationAt()?->toIso8601String(),
            'notes' => $this->notes,
            'has_signed_contract' => $this->signed_contract_path !== null,
            'signed_contract_original_name' => $this->signed_contract_original_name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
