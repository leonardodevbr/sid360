<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendInstallmentReminderJob;
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
            ->with(['sale.client'])
            ->get();

        foreach ($upcoming as $installment) {
            if ($installment->sale?->client?->phone) {
                SendInstallmentReminderJob::dispatch($installment, 'upcoming');
            }
        }

        $this->info("Lembretes de vencimento: {$upcoming->count()} enviados.");

        $overdueDate = $today->copy()->subDay();

        $overdue = Installment::query()
            ->where('status', Installment::STATUS_PENDING)
            ->whereDate('due_date', $overdueDate)
            ->where('type', '!=', Installment::TYPE_DOWN_PAYMENT)
            ->with(['sale.client'])
            ->get();

        foreach ($overdue as $installment) {
            if ($installment->sale?->client?->phone) {
                SendInstallmentReminderJob::dispatch($installment, 'overdue');
            }
        }

        $this->info("Avisos de atraso: {$overdue->count()} enviados.");

        return self::SUCCESS;
    }
}
