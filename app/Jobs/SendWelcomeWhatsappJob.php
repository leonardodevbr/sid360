<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Sale;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly Sale $sale) {}

    public function handle(WhatsappService $whatsapp): void
    {
        if (! Setting::get('whatsapp_notifications_enabled', true)) {
            return;
        }
        if (! Setting::get('whatsapp_welcome_enabled', true)) {
            return;
        }

        $this->sale->loadMissing(['client', 'lot.development', 'buyers']);

        $allBuyers = $this->sale->buyers->count() > 0
            ? $this->sale->buyers
            : collect([$this->sale->client]);

        $contractNo = str_pad((string) $this->sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.$this->sale->sale_date?->format('Y');

        $fmt = fn ($v) => 'R$ '.number_format((int) $v / 100, 2, ',', '.');

        $template = (string) Setting::get(
            'whatsapp_welcome_message',
            "Olá, *{nome}*! 🎉\nSua compra foi registrada!\nContrato: {contrato} · Lote: {lote}\nValor: {valor_total} · 1ª parcela: {primeira_parcela}"
        );

        foreach ($allBuyers as $buyer) {
            if (! $buyer->phone) {
                continue;
            }

            $vars = [
                'nome' => $buyer->name,
                'contrato' => $contractNo,
                'empreendimento' => $this->sale->lot?->development?->name ?? '–',
                'lote' => 'Quadra '.($this->sale->lot?->block ?? '?').' · Lote '.($this->sale->lot?->number ?? '?'),
                'valor_total' => $fmt($this->sale->total_value),
                'primeira_parcela' => $this->sale->first_due_date?->format('d/m/Y') ?? '–',
            ];

            $message = $whatsapp->interpolate($template, $vars);
            $whatsapp->send($buyer->phone, $message);
        }
    }
}
