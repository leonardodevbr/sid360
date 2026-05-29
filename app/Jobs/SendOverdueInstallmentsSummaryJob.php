<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Sale\SendOverdueWhatsappAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendOverdueInstallmentsSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly int $saleId) {}

    public function handle(SendOverdueWhatsappAction $action): void
    {
        $lock = Cache::lock("whatsapp-overdue-sale-{$this->saleId}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $action->execute(
                saleId: $this->saleId,
                forceResend: false,
                sendEmail: true,
            );
        } finally {
            $lock->release();
        }
    }
}
