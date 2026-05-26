<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OverdueInstallmentsMail;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOverdueInstallmentsSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

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
        $sale = Sale::query()
            ->with(['client', 'lot.development'])
            ->find($this->saleId);

        if (! $sale?->client) {
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

        $lines = $penalty->formatLinesForMessage($summary);
        $description = implode("\n", [
            "⚠️ *{$sale->client->name}*, você tem *{$summary['count']} parcela(s) em atraso* no contrato *{$contractNo}*:",
            '',
            $lines,
            '',
            "💰 Total em aberto: *{$fmt($summary['total_value_cents'])}*",
            "💰 Total corrigido (prev. p/ {$summary['payment_date']->format('d/m/Y')}): *{$fmt($summary['total_corrected_cents'])}*",
            '',
            '⚠️ Estimativa com multa de 2,5% ao mês (pró-rata por dia).',
            '',
            'Escolha uma opção abaixo 👇',
        ]);

        if (
            Setting::get('whatsapp_notifications_enabled', true)
            && Setting::get('whatsapp_overdue_enabled', true)
            && $sale->client->phone
        ) {
            $sections = [
                [
                    'title' => 'O que deseja fazer?',
                    'rows' => [
                        [
                            'rowId' => '1',
                            'title' => '✅ Vou regularizar em breve',
                            'description' => 'Confirmar ciência do débito',
                        ],
                        [
                            'rowId' => '2',
                            'title' => '💰 Quero PIX/boleto atualizado',
                            'description' => 'Receber valor corrigido e link de pagamento',
                        ],
                        [
                            'rowId' => '3',
                            'title' => '🤝 Preciso negociar',
                            'description' => 'Falar diretamente com o corretor',
                        ],
                    ],
                ],
            ];

            $sent = $whatsapp->sendListAndRecord(
                phone: $sale->client->phone,
                description: $description,
                buttonText: 'Ver opções',
                sections: $sections,
                type: InstallmentInteraction::TYPE_OVERDUE,
                installmentId: $overdue->first()?->id !== null ? (int) $overdue->first()->id : null,
                saleId: (int) $sale->id,
                clientId: (int) $sale->client->id,
                meta: [
                    'installment_ids' => $overdue->pluck('id')->toArray(),
                    'total_value_cents' => $summary['total_value_cents'],
                    'total_corrected_cents' => $summary['total_corrected_cents'],
                    'count' => $summary['count'],
                ],
            );

            if ($sent) {
                DB::transaction(function () use ($overdue): void {
                    Installment::query()
                        ->whereIn('id', $overdue->pluck('id'))
                        ->whereNull('whatsapp_overdue_sent_at')
                        ->update(['whatsapp_overdue_sent_at' => now()]);
                });
            }
        }

        if (
            Setting::get('email_notifications_enabled', true)
            && Setting::get('email_overdue_enabled', true)
            && filled($sale->client->email)
        ) {
            $overdueList = $overdue->map(function (Installment $i): array {
                $daysOverdue = (int) now()->startOfDay()->diffInDays($i->due_date?->startOfDay(), false) * -1;

                return [
                    'number' => $i->type === Installment::TYPE_DOWN_PAYMENT ? 'Entrada' : 'Parcela '.$i->number,
                    'due_date' => $i->due_date?->format('d/m/Y') ?? '–',
                    'value' => 'R$ '.number_format((int) $i->value / 100, 2, ',', '.'),
                    'days_overdue' => max(0, $daysOverdue),
                ];
            })->toArray();

            try {
                Mail::to($sale->client->email)->queue(new OverdueInstallmentsMail(
                    clientName: $sale->client->name,
                    contractNo: $contractNo,
                    totalValue: $fmt($summary['total_value_cents']),
                    totalCorrected: $fmt($summary['total_corrected_cents']),
                    paymentDate: $summary['payment_date']->format('d/m/Y'),
                    overdueList: $overdueList,
                ));
            } catch (\Exception $e) {
                Log::error('OverdueInstallmentsMail failed', [
                    'sale_id' => $this->saleId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
