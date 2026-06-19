<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'type' => $this->type,
            'number' => $this->number,
            'due_date' => $this->due_date?->toDateString(),
            'value' => (int) $this->value,
            'paid_at' => $this->paid_at?->toDateString(),
            'status' => $this->displayStatus(),
            'payment_method' => $this->payment_method,
            'payment_method_label' => Installment::paymentMethodLabel($this->payment_method),
            'payment_method_description' => $this->payment_method_description,
            'whatsapp_reminder_sent_at' => $this->whatsapp_reminder_sent_at?->toIso8601String(),
            'whatsapp_overdue_sent_at' => $this->whatsapp_overdue_sent_at?->toIso8601String(),
            'whatsapp_last_notification_at' => $this->lastWhatsappNotificationAt()?->toIso8601String(),
            'efi_charge_id' => $this->efi_charge_id,
            'efi_txid' => $this->efi_txid,
            'efi_barcode' => $this->efi_barcode,
            'efi_pdf_url' => $this->efi_pdf_url,
            'efi_pix_copia_cola' => $this->efi_pix_copia_cola,
            'efi_pix_qrcode' => $this->efi_pix_qrcode,
            'efi_payment_type' => $this->efi_payment_type,
        ];
    }
}
