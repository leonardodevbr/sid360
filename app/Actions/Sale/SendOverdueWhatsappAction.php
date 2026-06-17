<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Mail\OverdueInstallmentsMail;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\InstallmentPenaltyService;
use App\Support\WhatsappBotMessageFooter;
use App\Services\WhatsappService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOverdueWhatsappAction
{
    public function __construct(
        private readonly WhatsappService $whatsapp,
        private readonly InstallmentPenaltyService $penalty,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     sent: bool,
     *     overdue_count: int,
     *     message: string,
     *     error?: string
     * }
     */
    public function execute(int $saleId, bool $forceResend = false, bool $sendEmail = false): array
    {
        $sale = Sale::query()
            ->with(['client', 'lot.development'])
            ->find($saleId);

        if (! $sale?->client) {
            return $this->failure('Venda ou cliente não encontrado.');
        }

        $client = $sale->client;

        if (! filled($client->phone)) {
            return $this->failure('Cliente sem telefone/WhatsApp cadastrado.');
        }

        if (! $client->acceptsWhatsappNotifications()) {
            return $this->failure('Cliente optou por não receber mensagens automáticas no WhatsApp.');
        }

        $overdue = $this->resolveOverdueInstallments($sale, $forceResend);

        if ($overdue->isEmpty()) {
            $message = $forceResend
                ? 'Não há parcelas em atraso neste contrato.'
                : 'Não há parcelas em atraso pendentes de notificação automática.';

            return $this->failure($message);
        }

        if (! Setting::get('whatsapp_notifications_enabled', true)) {
            return $this->failure('Notificações WhatsApp estão desativadas nas configurações.');
        }

        if (! $forceResend && ! Setting::get('whatsapp_overdue_enabled', true)) {
            return $this->failure('Aviso automático de atraso está desativado nas configurações.');
        }

        $summary = $this->penalty->summarize($overdue);
        $fmt = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));

        $lines = $this->penalty->formatLinesForMessage($summary);
        $description = implode("\n", [
            "⚠️ *{$client->name}*, você tem *{$summary['count']} parcela(s) em atraso* no contrato *{$contractNo}*:",
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

        $sent = $this->whatsapp->sendListAndRecord(
            phone: (string) $client->phone,
            description: $description,
            buttonText: 'Ver opções',
            sections: $sections,
            type: InstallmentInteraction::TYPE_OVERDUE,
            installmentId: $overdue->first()?->id !== null ? (int) $overdue->first()->id : null,
            saleId: (int) $sale->id,
            clientId: (int) $client->id,
            meta: [
                'installment_ids' => $overdue->pluck('id')->toArray(),
                'total_value_cents' => $summary['total_value_cents'],
                'total_corrected_cents' => $summary['total_corrected_cents'],
                'count' => $summary['count'],
                'manual' => $forceResend,
                'resent' => $forceResend,
            ],
            footer: WhatsappBotMessageFooter::automatic(),
        );

        if (! $sent) {
            return $this->failure('Não foi possível enviar pelo WhatsApp. Verifique o WPPConnect.');
        }

        DB::transaction(function () use ($overdue, $forceResend): void {
            $query = Installment::query()->whereIn('id', $overdue->pluck('id'));

            if (! $forceResend) {
                $query->whereNull('whatsapp_overdue_sent_at');
            }

            $query->update(['whatsapp_overdue_sent_at' => now()]);
        });

        if ($sendEmail && filled($client->email) && Setting::get('email_notifications_enabled', true) && Setting::get('email_overdue_enabled', true)) {
            $this->sendOverdueEmail($sale, $client, $contractNo, $overdue, $summary, $fmt);
        }

        $successMessage = $forceResend
            ? "Cobrança de atraso reenviada para {$client->phone} ({$summary['count']} parcela(s))."
            : "Cobrança de atraso enviada para {$client->phone} ({$summary['count']} parcela(s)).";

        return [
            'ok' => true,
            'sent' => true,
            'overdue_count' => $summary['count'],
            'message' => $successMessage,
        ];
    }

    /**
     * @return Collection<int, Installment>
     */
    private function resolveOverdueInstallments(Sale $sale, bool $forceResend): Collection
    {
        $query = Installment::query()
            ->where('sale_id', $sale->id)
            ->overdue()
            ->where('type', '!=', Installment::TYPE_DOWN_PAYMENT)
            ->orderBy('due_date');

        if (! $forceResend) {
            $query->whereNull('whatsapp_overdue_sent_at');
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Installment>  $overdue
     * @param  array<string, mixed>  $summary
     */
    private function sendOverdueEmail(
        Sale $sale,
        \App\Models\Client $client,
        string $contractNo,
        Collection $overdue,
        array $summary,
        callable $fmt,
    ): void {
        $overdueList = $overdue->map(function (Installment $installment): array {
            $daysOverdue = (int) now()->startOfDay()->diffInDays($installment->due_date?->startOfDay(), false) * -1;

            return [
                'number' => $installment->type === Installment::TYPE_DOWN_PAYMENT
                    ? 'Entrada'
                    : 'Parcela '.$installment->number,
                'due_date' => $installment->due_date?->format('d/m/Y') ?? '–',
                'value' => 'R$ '.number_format((int) $installment->value / 100, 2, ',', '.'),
                'days_overdue' => max(0, $daysOverdue),
            ];
        })->toArray();

        try {
            Mail::to($client->email)->queue(new OverdueInstallmentsMail(
                clientName: $client->name,
                contractNo: $contractNo,
                totalValue: $fmt($summary['total_value_cents']),
                totalCorrected: $fmt($summary['total_corrected_cents']),
                paymentDate: $summary['payment_date']->format('d/m/Y'),
                overdueList: $overdueList,
            ));
        } catch (\Exception $e) {
            Log::error('OverdueInstallmentsMail failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{ok: false, sent: false, overdue_count: 0, message: string, error: string}
     */
    private function failure(string $error): array
    {
        return [
            'ok' => false,
            'sent' => false,
            'overdue_count' => 0,
            'message' => $error,
            'error' => $error,
        ];
    }
}
