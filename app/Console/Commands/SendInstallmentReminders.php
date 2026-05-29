<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendInstallmentReminderJob;
use App\Jobs\SendOverdueInstallmentsSummaryJob;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendInstallmentReminders extends Command
{
    protected $signature = 'installments:send-reminders';

    protected $description = 'Envia lembretes de parcelas via WhatsApp (upcoming + overdue)';

    public function handle(): int
    {
        $today = Carbon::today();

        $daysBefore = (int) Setting::get('whatsapp_reminder_days_before', 3);
        $upcomingDate = $today->copy()->addDays($daysBefore);

        $upcoming = Installment::query()
            ->where('status', Installment::STATUS_PENDING)
            ->whereDate('due_date', $upcomingDate)
            ->where('type', '!=', Installment::TYPE_DOWN_PAYMENT)
            ->whereNull('whatsapp_reminder_sent_at')
            ->with(['sale.client'])
            ->get();

        foreach ($upcoming as $installment) {
            if ($installment->sale?->client?->acceptsWhatsappNotifications()) {
                SendInstallmentReminderJob::dispatchSync($installment);
            }
        }

        $this->info("Lembretes de vencimento: {$upcoming->count()} enviados.");

        $overdueSaleIds = Installment::query()
            ->overdue()
            ->where('type', '!=', Installment::TYPE_DOWN_PAYMENT)
            ->whereNull('whatsapp_overdue_sent_at')
            ->whereHas('sale.client', function ($q): void {
                $q->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->where(function ($statusQuery): void {
                        $statusQuery->whereNull('whatsapp_status')
                            ->orWhere('whatsapp_status', '!=', Client::WHATSAPP_STATUS_NONE);
                    });
            })
            ->distinct()
            ->pluck('sale_id');

        foreach ($overdueSaleIds as $saleId) {
            SendOverdueInstallmentsSummaryJob::dispatchSync((int) $saleId);
        }

        $this->info("Avisos de atraso: {$overdueSaleIds->count()} contrato(s) enfileirado(s).");

        return self::SUCCESS;
    }
}
