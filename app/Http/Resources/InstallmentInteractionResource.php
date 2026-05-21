<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentInteractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_id' => $this->installment_id,
            'sale_id' => $this->sale_id,
            'client_id' => $this->client_id,
            'phone' => $this->phone,
            'direction' => $this->direction,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'message' => $this->message,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function typeLabel(): string
    {
        return match ($this->type) {
            'reminder' => 'Lembrete enviado',
            'overdue' => 'Aviso de atraso enviado',
            'welcome' => 'Boas-vindas enviada',
            'boleto_link' => 'Link de pagamento enviado',
            'negotiate_forward' => 'Encaminhado para negociação',
            'reply_acknowledge' => 'Cliente: vai regularizar',
            'reply_boleto' => 'Cliente: solicitou boleto/PIX',
            'reply_negotiate' => 'Cliente: quer negociar',
            'reply_unknown' => 'Cliente: resposta não reconhecida',
            default => $this->type,
        };
    }
}
