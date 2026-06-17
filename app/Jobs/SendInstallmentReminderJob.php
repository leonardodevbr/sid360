<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\InstallmentReminderMail;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Setting;
use App\Support\WhatsappBotMessageFooter;
use App\Support\WhatsappReminderButtons;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInstallmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly Installment $installment) {}

    public function handle(WhatsappService $whatsapp): void
    {
        $lock = Cache::lock("whatsapp-reminder-installment-{$this->installment->id}", 120);

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
        $this->installment->refresh();

        if ($this->installment->whatsapp_reminder_sent_at !== null) {
            return;
        }

        $sale = $this->installment->sale()->with(['client', 'lot.development'])->first();
        $client = $sale?->client;

        if (! $client) {
            return;
        }

        $fmt = fn ($v) => 'R$ '.number_format((int) $v / 100, 2, ',', '.');

        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.$sale->sale_date?->format('Y');

        $lotDescription = 'Q'.($sale->lot?->block ?? '?').' · L'.($sale->lot?->number ?? '?');

        $daysBefore = (int) Setting::get('whatsapp_reminder_days_before', 3);

        if (
            Setting::get('whatsapp_notifications_enabled', true)
            && Setting::get('whatsapp_reminder_enabled', true)
            && $client->acceptsWhatsappNotifications()
        ) {
            $vars = [
                'nome' => $client->name,
                'contrato' => $contractNo,
                'lote' => $lotDescription,
                'valor' => $fmt($this->installment->value),
                'vencimento' => $this->installment->due_date?->format('d/m/Y') ?? '–',
                'dias' => (string) $daysBefore,
            ];

            $template = (string) Setting::get(
                'whatsapp_reminder_message',
                "Olá, *{nome}*! Sua parcela vence em {dias} dias.\nContrato: {contrato} · Lote: {lote}\nValor: {valor} · Vencimento: {vencimento}"
            );

            $message = $whatsapp->interpolate($template, $vars);

            $sent = $whatsapp->sendQuickReplyButtonsAndRecord(
                phone: (string) $client->phone,
                message: $message,
                buttons: WhatsappReminderButtons::buttons(),
                type: InstallmentInteraction::TYPE_REMINDER,
                installmentId: (int) $this->installment->id,
                saleId: (int) $sale->id,
                clientId: (int) $client->id,
                meta: ['days_before' => $daysBefore],
                extraOptions: WhatsappBotMessageFooter::wppconnectOptions(),
            );

            if (! $sent) {
                $sent = $whatsapp->sendAndRecord(
                    phone: (string) $client->phone,
                    message: $message,
                    type: InstallmentInteraction::TYPE_REMINDER,
                    installmentId: (int) $this->installment->id,
                    saleId: (int) $sale->id,
                    clientId: (int) $client->id,
                    meta: ['days_before' => $daysBefore, 'format' => 'text'],
                    wppconnectOptions: WhatsappBotMessageFooter::wppconnectOptions(),
                );
            }

            if ($message !== '' && $sent) {
                $this->installment->update(['whatsapp_reminder_sent_at' => now()]);
            }
        }

        if (
            Setting::get('email_notifications_enabled', true)
            && Setting::get('email_reminder_enabled', true)
            && filled($client->email)
        ) {
            try {
                Mail::to($client->email)->queue(new InstallmentReminderMail(
                    installment: $this->installment,
                    clientName: $client->name,
                    contractNo: $contractNo,
                    lotDescription: $lotDescription,
                    value: $fmt($this->installment->value),
                    dueDate: $this->installment->due_date?->format('d/m/Y') ?? '–',
                    daysBefore: $daysBefore,
                ));
            } catch (\Exception $e) {
                Log::error('InstallmentReminderMail failed', [
                    'installment_id' => $this->installment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
