<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

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
            'installments_label' => $this->resolveInstallmentsLabel($request),
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
            'reply_acknowledge_response' => 'Resposta: confirmação enviada',
            'reply_boleto_response' => 'Resposta: instruções de pagamento',
            'reply_negotiate_response' => 'Resposta: encaminhado ao corretor',
            'reply_unknown_response' => 'Resposta: opção não reconhecida',
            default => $this->type,
        };
    }

    private function resolveInstallmentsLabel(Request $request): ?string
    {
        /** @var Collection<int, Installment>|null $map */
        $map = $request->attributes->get('installments_by_id');

        if (! $map instanceof Collection) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_merge(
            $this->installment_id ? [$this->installment_id] : [],
            is_array($this->meta['installment_ids'] ?? null) ? $this->meta['installment_ids'] : [],
        ))));

        if ($ids === []) {
            return null;
        }

        $labels = collect($ids)
            ->map(fn (int $id): ?string => $this->formatInstallmentLabel($map->get($id)))
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return null;
        }

        return $labels->join(' · ');
    }

    private function formatInstallmentLabel(?Installment $installment): ?string
    {
        if (! $installment) {
            return null;
        }

        if ($installment->type === Installment::TYPE_DOWN_PAYMENT) {
            return 'Entrada';
        }

        $number = str_pad((string) $installment->number, 2, '0', STR_PAD_LEFT);
        $due = $installment->due_date?->format('d/m/Y');

        return $due ? "Parcela {$number} (venc. {$due})" : "Parcela {$number}";
    }
}
