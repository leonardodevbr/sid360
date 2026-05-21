<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Installment;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInstallmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private readonly Installment $installment,
        private readonly string $type // 'upcoming' | 'overdue' | 'welcome'
    ) {}

    public function handle(WhatsappService $whatsapp): void
    {
        $sale = $this->installment->sale()->with(['client', 'lot.development'])->first();
        $client = $sale?->client;

        if (! $client?->phone) {
            return;
        }

        $fmt = fn ($v) => 'R$ '.number_format((int) $v / 100, 2, ',', '.');

        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.$sale->sale_date?->format('Y');

        $vars = [
            'nome' => $client->name,
            'contrato' => $contractNo,
            'lote' => 'Q'.($sale->lot?->block ?? '?').' · L'.($sale->lot?->number ?? '?'),
            'valor' => $fmt($this->installment->value),
            'vencimento' => $this->installment->due_date?->format('d/m/Y') ?? '–',
            'dias' => (string) Setting::get('whatsapp_reminder_days_before', '3'),
        ];

        $message = match ($this->type) {
            'upcoming' => (function () use ($whatsapp, $vars): string {
                if (! Setting::get('whatsapp_reminder_enabled', true)) {
                    return '';
                }
                $template = (string) Setting::get(
                    'whatsapp_reminder_message',
                    "Olá, *{nome}*! Sua parcela vence em {dias} dias.\nContrato: {contrato} · Lote: {lote}\nValor: {valor} · Vencimento: {vencimento}"
                );

                return $whatsapp->interpolate($template, $vars);
            })(),

            'overdue' => (function () use ($whatsapp, $vars): string {
                if (! Setting::get('whatsapp_overdue_enabled', true)) {
                    return '';
                }
                $template = (string) Setting::get(
                    'whatsapp_overdue_message',
                    "Olá, *{nome}*! Parcela em atraso.\nContrato: {contrato} · Valor: {valor}"
                );

                return $whatsapp->interpolate($template, $vars);
            })(),

            default => '',
        };

        if ($message === '' || ! Setting::get('whatsapp_notifications_enabled', true)) {
            return;
        }

        $whatsapp->send($client->phone, $message);
    }
}
