<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\WhatsappService;

class SendWelcomeWhatsappAction
{
    public function __construct(private readonly WhatsappService $whatsapp) {}

    /**
     * Envia (ou reenvia, com $forceResend) a mensagem de boas-vindas da venda
     * via WhatsApp pro(s) comprador(es). Usa o mesmo template configurável
     * (`whatsapp_welcome_message`) do envio automático feito por
     * SendWelcomeWhatsappJob ao registrar a venda — esta action existe pra
     * permitir reenvio manual a partir da tela da venda (ex.: corretor
     * cadastrou o telefone errado e corrigiu depois, ou o cliente apagou a
     * conversa).
     *
     * @return array{ok: bool, sent: bool, message: string, error?: string}
     */
    public function execute(Sale $sale, bool $forceResend = true): array
    {
        if ($sale->status === Sale::STATUS_CANCELLED) {
            return $this->failure('Não é possível enviar boas-vindas para uma venda cancelada.');
        }

        $sale->loadMissing(['client', 'lot.development', 'lots.zone', 'buyers']);

        $allBuyers = $sale->buyers->count() > 0
            ? $sale->buyers
            : collect([$sale->client])->filter();

        if ($allBuyers->isEmpty()) {
            return $this->failure('Venda sem cliente/comprador vinculado.');
        }

        $eligibleBuyers = $allBuyers->filter(fn ($buyer) => $buyer->acceptsWhatsappNotifications());

        if ($eligibleBuyers->isEmpty()) {
            return $this->failure('Nenhum comprador desta venda tem WhatsApp cadastrado/habilitado para notificações.');
        }

        if (! $forceResend && $sale->whatsapp_welcome_sent_at !== null) {
            return $this->failure('A mensagem de boas-vindas já foi enviada para esta venda.');
        }

        if (! Setting::get('whatsapp_notifications_enabled', true)) {
            return $this->failure('Notificações WhatsApp estão desativadas nas configurações.');
        }

        if (! $forceResend && ! Setting::get('whatsapp_welcome_enabled', true)) {
            return $this->failure('Mensagem de boas-vindas automática está desativada nas configurações.');
        }

        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

        $fmt = fn ($v) => 'R$ '.number_format((int) $v / 100, 2, ',', '.');
        $lotDescription = $this->buildLotDescription($sale);

        $template = (string) Setting::get(
            'whatsapp_welcome_message',
            "Olá, *{nome}*! 🎉\nSua compra foi registrada!\nContrato: {contrato} · Lote: {lote}\nValor: {valor_total} · 1ª parcela: {primeira_parcela}"
        );

        $sentPhones = [];

        foreach ($eligibleBuyers as $buyer) {
            $vars = [
                'nome' => $buyer->name,
                'contrato' => $contractNo,
                'empreendimento' => $sale->lot?->development?->name ?? '–',
                'lote' => $lotDescription,
                'valor_total' => $fmt($sale->total_value),
                'primeira_parcela' => $sale->first_due_date?->format('d/m/Y') ?? '–',
            ];

            $message = $this->whatsapp->interpolate($template, $vars);

            $sent = $this->whatsapp->sendAndRecord(
                phone: (string) $buyer->phone,
                message: $message,
                type: InstallmentInteraction::TYPE_WELCOME,
                saleId: (int) $sale->id,
                clientId: (int) $buyer->id,
                meta: ['manual' => true, 'resent' => $forceResend],
            );

            if ($sent) {
                $sentPhones[] = $buyer->phone;
            }
        }

        if ($sentPhones === []) {
            return $this->failure('Não foi possível enviar pelo WhatsApp. Verifique o WPPConnect.');
        }

        $sale->update(['whatsapp_welcome_sent_at' => now()]);

        $successMessage = $forceResend
            ? 'Mensagem de boas-vindas reenviada para '.implode(', ', $sentPhones).'.'
            : 'Mensagem de boas-vindas enviada para '.implode(', ', $sentPhones).'.';

        return [
            'ok' => true,
            'sent' => true,
            'message' => $successMessage,
        ];
    }

    /**
     * Descreve o(s) lote(s) da venda. Mesma lógica usada em
     * SendWelcomeWhatsappJob — uma venda pode ter mais de um lote (lotes
     * vizinhos comprados juntos, via pivot sale_lots); nesse caso a mensagem
     * lista todos em vez de mostrar só o lote principal.
     */
    private function buildLotDescription(Sale $sale): string
    {
        $lots = $sale->relationLoaded('lots') && $sale->lots->isNotEmpty()
            ? $sale->lots
            : collect([$sale->lot])->filter();

        if ($lots->count() <= 1) {
            $lot = $lots->first() ?? $sale->lot;
            $block = $lot?->block ?? $lot?->zone?->name ?? '?';

            return $block.' · Lote '.($lot?->number ?? '?');
        }

        $byBlock = $lots->groupBy(fn ($lot) => $lot->block ?? $lot->zone?->name ?? '?');

        if ($byBlock->count() === 1) {
            return $byBlock->keys()->first().' · Lotes '.$lots->pluck('number')->join(', ');
        }

        return $lots
            ->map(fn ($lot) => ($lot->block ?? $lot->zone?->name ?? '?').' · Lote '.$lot->number)
            ->join(', ');
    }

    /**
     * @return array{ok: false, sent: false, message: string, error: string}
     */
    private function failure(string $error): array
    {
        return [
            'ok' => false,
            'sent' => false,
            'message' => $error,
            'error' => $error,
        ];
    }
}
