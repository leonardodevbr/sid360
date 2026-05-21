<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Installment;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\InstallmentPenaltyService;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendOverdueInstallmentsSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    private const DEFAULT_TEMPLATE = <<<'TEXT'
Olá, *{nome}*! ⚠️

Identificamos *{qtd_atrasadas} parcela(s) em atraso* no contrato *{contrato}*:

{parcelas_atrasadas}

💰 Total em aberto: *{valor_total_atraso}*
💰 Total corrigido (prev. p/ {data_pagamento_prevista}): *{valor_total_corrigido}*

⚠️ Estimativa com multa de 2,5% ao mês (pró-rata por dia).

Para regularizar: 📱 (74) 9 8823-0151
_Sid360 Imóveis_
TEXT;

    public function __construct(private readonly int $saleId) {}

    public function handle(WhatsappService $whatsapp, InstallmentPenaltyService $penalty): void
    {
        $lock = Cache::lock("whatsapp-overdue-sale-{$this->saleId}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->send($whatsapp, $penalty);
        } finally {
            $lock->release();
        }
    }

    private function send(WhatsappService $whatsapp, InstallmentPenaltyService $penalty): void
    {
        if (! Setting::get('whatsapp_notifications_enabled', true)) {
            return;
        }
        if (! Setting::get('whatsapp_overdue_enabled', true)) {
            return;
        }

        $sale = Sale::query()
            ->with(['client', 'lot.development'])
            ->find($this->saleId);

        if (! $sale?->client?->phone) {
            return;
        }

        $overdue = Installment::query()
            ->where('sale_id', $this->saleId)
            ->overdue()
            ->where('type', '!=', Installment::TYPE_DOWN_PAYMENT)
            ->whereNull('whatsapp_overdue_sent_at')
            ->orderBy('due_date')
            ->get();

        if ($overdue->isEmpty()) {
            return;
        }

        $summary = $penalty->summarize($overdue);
        $fmt = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');

        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.$sale->sale_date?->format('Y');

        $oldest = $summary['lines'][0] ?? null;

        $vars = [
            'nome' => $sale->client->name,
            'contrato' => $contractNo,
            'lote' => 'Q'.($sale->lot?->block ?? '?').' · L'.($sale->lot?->number ?? '?'),
            'valor' => $oldest['value_formatted'] ?? $fmt($summary['total_value_cents']),
            'vencimento' => $oldest['due_date'] ?? '–',
            'dias_atraso' => (string) $summary['max_days_overdue'],
            'qtd_atrasadas' => (string) $summary['count'],
            'parcelas_atrasadas' => $penalty->formatLinesForMessage($summary),
            'valor_total_atraso' => $fmt($summary['total_value_cents']),
            'valor_total_corrigido' => $fmt($summary['total_corrected_cents']),
            'valor_corrigido' => $summary['count'] === 1
                ? ($oldest['corrected_formatted'] ?? $fmt($summary['total_corrected_cents']))
                : $fmt($summary['total_corrected_cents']),
            'data_pagamento_prevista' => $summary['payment_date']->format('d/m/Y'),
        ];

        $template = $this->resolveTemplate();
        $message = $whatsapp->interpolate($template, $vars);

        if ($message === '') {
            return;
        }

        if (! $whatsapp->send($sale->client->phone, $message)) {
            return;
        }

        DB::transaction(function () use ($overdue): void {
            Installment::query()
                ->whereIn('id', $overdue->pluck('id'))
                ->whereNull('whatsapp_overdue_sent_at')
                ->update(['whatsapp_overdue_sent_at' => now()]);
        });
    }

    private function resolveTemplate(): string
    {
        $saved = (string) Setting::get('whatsapp_overdue_message', '');

        if ($saved === '' || ! str_contains($saved, '{parcelas_atrasadas}')) {
            return self::DEFAULT_TEMPLATE;
        }

        return $saved;
    }
}
