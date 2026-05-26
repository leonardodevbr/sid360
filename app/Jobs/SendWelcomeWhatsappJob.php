<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WelcomeSaleMail;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWelcomeWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly Sale $sale) {}

    public function handle(WhatsappService $whatsapp): void
    {
        $lock = Cache::lock("whatsapp-welcome-sale-{$this->sale->id}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->send($whatsapp);
        } finally {
            $lock->release();
        }
    }

    private function send(WhatsappService $whatsapp): void
    {
        $this->sale->refresh();
        $this->sale->loadMissing(['client', 'lot.development', 'buyers']);

        $allBuyers = $this->sale->buyers->count() > 0
            ? $this->sale->buyers
            : collect([$this->sale->client]);

        $contractNo = str_pad((string) $this->sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.$this->sale->sale_date?->format('Y');

        $fmt = fn ($v) => 'R$ '.number_format((int) $v / 100, 2, ',', '.');

        $lotDescription = 'Quadra '.($this->sale->lot?->block ?? '?').' · Lote '.($this->sale->lot?->number ?? '?');

        if (
            $this->sale->whatsapp_welcome_sent_at === null
            && Setting::get('whatsapp_notifications_enabled', true)
            && Setting::get('whatsapp_welcome_enabled', true)
        ) {
            $template = (string) Setting::get(
                'whatsapp_welcome_message',
                "Olá, *{nome}*! 🎉\nSua compra foi registrada!\nContrato: {contrato} · Lote: {lote}\nValor: {valor_total} · 1ª parcela: {primeira_parcela}"
            );

            $sentAny = false;

            foreach ($allBuyers as $buyer) {
                if (! $buyer->phone) {
                    continue;
                }

                $vars = [
                    'nome' => $buyer->name,
                    'contrato' => $contractNo,
                    'empreendimento' => $this->sale->lot?->development?->name ?? '–',
                    'lote' => $lotDescription,
                    'valor_total' => $fmt($this->sale->total_value),
                    'primeira_parcela' => $this->sale->first_due_date?->format('d/m/Y') ?? '–',
                ];

                $message = $whatsapp->interpolate($template, $vars);

                if ($whatsapp->send($buyer->phone, $message)) {
                    $sentAny = true;
                }
            }

            if ($sentAny) {
                $this->sale->update(['whatsapp_welcome_sent_at' => now()]);
            }
        }

        if (Setting::get('email_notifications_enabled', true) && Setting::get('email_welcome_enabled', true)) {
            foreach ($allBuyers->filter(fn ($b) => filled($b->email)) as $buyer) {
                try {
                    Mail::to($buyer->email)->queue(new WelcomeSaleMail(
                        sale: $this->sale,
                        buyerName: $buyer->name,
                        contractNo: $contractNo,
                        lotDescription: $lotDescription,
                        totalValue: $fmt($this->sale->total_value),
                        firstDueDate: $this->sale->first_due_date?->format('d/m/Y') ?? '–',
                    ));
                } catch (\Exception $e) {
                    Log::error('WelcomeSaleMail failed', [
                        'buyer_id' => $buyer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
