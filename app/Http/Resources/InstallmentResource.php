<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'whatsapp_reminder_sent_at' => $this->whatsapp_reminder_sent_at?->toIso8601String(),
            'whatsapp_overdue_sent_at' => $this->whatsapp_overdue_sent_at?->toIso8601String(),
            'whatsapp_last_notification_at' => $this->lastWhatsappNotificationAt()?->toIso8601String(),
        ];
    }
}
