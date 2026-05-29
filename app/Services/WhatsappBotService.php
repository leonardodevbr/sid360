<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Installment\SendInstallmentBoletoWhatsappAction;
use App\Actions\Installment\SendInstallmentPixWhatsappAction;
use App\Actions\Sale\GenerateSaleContractPdfAction;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WhatsappBotService
{
    public const COMMAND_MENU = 'menu';

    public const COMMAND_PAYMENT = 'payment';

    public const COMMAND_BALANCE = 'balance';

    public const COMMAND_STATEMENT = 'statement';

    public const COMMAND_CONTRACT = 'contract';

    public const COMMAND_SUPPORT = 'support';

    public const COMMAND_UNKNOWN = 'unknown';

    public function __construct(
        private readonly WhatsappService $whatsapp,
        private readonly SendInstallmentPixWhatsappAction $sendPix,
        private readonly SendInstallmentBoletoWhatsappAction $sendBoleto,
        private readonly GenerateSaleContractPdfAction $generateContract,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Client $client, string $phone, string $body, array $payload = []): void
    {
        if (! Setting::get('whatsapp_bot_enabled', true)) {
            return;
        }

        if (! $client->acceptsWhatsappNotifications()) {
            $this->recordInbound($client, $phone, $body, self::COMMAND_UNKNOWN, $payload);
            $this->sendBotResponse(
                $client,
                $phone,
                'Este número está cadastrado para não receber mensagens automáticas. Fale com a corretora para reativar.',
                self::COMMAND_UNKNOWN,
            );

            return;
        }

        [$command, $argument] = $this->parseCommand($body);

        $this->recordInbound($client, $phone, $body, $command, $payload, $argument);

        match ($command) {
            self::COMMAND_MENU => $this->sendMenu($client, $phone),
            self::COMMAND_PAYMENT => $this->handlePayment($client, $phone),
            self::COMMAND_BALANCE => $this->handleBalance($client, $phone),
            self::COMMAND_STATEMENT => $this->handleStatement($client, $phone),
            self::COMMAND_CONTRACT => $this->handleContract($client, $phone, $argument),
            self::COMMAND_SUPPORT => $this->handleSupport($client, $phone),
            default => $this->sendMenu($client, $phone, unknown: true),
        };
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public function parseCommand(string $body): array
    {
        $normalized = mb_strtolower(trim($body));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if ($normalized === '') {
            return [self::COMMAND_UNKNOWN, null];
        }

        if ($this->matchesAny($normalized, ['menu', 'ajuda', 'help', 'comandos', 'opcoes', 'opções'])) {
            return [self::COMMAND_MENU, null];
        }

        if ($this->matchesAny($normalized, ['oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'hey', 'hello'])) {
            return [self::COMMAND_MENU, null];
        }

        if ($this->matchesPaymentCommand($normalized)) {
            return [self::COMMAND_PAYMENT, null];
        }

        if ($this->matchesAny($normalized, ['saldo', 'pendente', 'pendentes', 'parcelas', 'devo', 'quanto devo'])) {
            return [self::COMMAND_BALANCE, null];
        }

        if ($this->matchesAny($normalized, ['extrato', 'historico', 'histórico', 'pagamentos', 'pago', 'pagos'])) {
            return [self::COMMAND_STATEMENT, null];
        }

        if ($this->matchesSupportCommand($normalized)) {
            return [self::COMMAND_SUPPORT, null];
        }

        if (preg_match('/^contrato(?:\s+(.+))?$/u', $normalized, $matches)) {
            $argument = isset($matches[1]) ? trim($matches[1]) : null;

            return [self::COMMAND_CONTRACT, $argument !== '' ? $argument : null];
        }

        return [self::COMMAND_UNKNOWN, null];
    }

    private function matchesPaymentCommand(string $normalized): bool
    {
        if ($this->matchesAny($normalized, ['2via', '2ª via', '2a via', 'segunda via', 'pagar', 'pagamento'])) {
            return true;
        }

        return preg_match('/\b(pix|boleto)\b/u', $normalized) === 1;
    }

    private function matchesSupportCommand(string $normalized): bool
    {
        return $this->matchesAny($normalized, [
            'atendimento',
            'falar com sid',
            'falar com o sid',
            'corretor',
            'negociar',
            'humano',
            'suporte',
        ]);
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($haystack === $needle || str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function sendMenu(Client $client, string $phone, bool $unknown = false): void
    {
        $template = (string) Setting::get(
            'whatsapp_bot_menu_message',
            "Olá, *{nome}*! Sou o assistente *Sid360*.\n\nDigite um comando:\n\n*2ª via* — receber PIX ou boleto\n*saldo* — parcelas pendentes\n*extrato* — histórico de pagamentos\n*contrato* — PDF do contrato\n*atendimento* — falar com o corretor\n\nPortal: {portal_url}",
        );

        $prefix = $unknown
            ? "Não entendi sua mensagem.\n\n"
            : '';

        $message = $prefix.$this->whatsapp->interpolate($template, [
            'nome' => $client->name,
            'portal_url' => $this->portalUrl(),
        ]);

        $this->sendBotResponse($client, $phone, $message, self::COMMAND_MENU);
    }

    private function handlePayment(Client $client, string $phone): void
    {
        $installment = $this->nextPayableInstallment($client);

        if ($installment === null) {
            $this->sendBotResponse(
                $client,
                $phone,
                "✅ *{$client->name}*, não encontramos parcelas em aberto nos seus contratos.\n\nPortal: {$this->portalUrl()}",
                self::COMMAND_PAYMENT,
            );

            return;
        }

        $sent = $this->sendPix->execute(
            installment: $installment,
            phone: $phone,
            interactionType: InstallmentInteraction::TYPE_BOT_PAYMENT,
        );

        if ($sent) {
            return;
        }

        $boleto = $this->sendBoleto->execute(
            installment: $installment,
            phone: $phone,
            interactionType: InstallmentInteraction::TYPE_BOT_PAYMENT,
        );

        if ($boleto['ok']) {
            return;
        }

        $this->sendBotResponse(
            $client,
            $phone,
            "Não foi possível gerar o pagamento agora.\n\nAcesse o portal:\n🔗 {$this->portalUrl()}\n\nOu digite *atendimento* para falar com o corretor.",
            self::COMMAND_PAYMENT,
            saleId: $installment->sale_id,
            installmentId: $installment->id,
        );
    }

    private function handleBalance(Client $client, string $phone): void
    {
        $sales = $this->activeSales($client);

        if ($sales->isEmpty()) {
            $this->sendBotResponse(
                $client,
                $phone,
                "Não encontramos contratos ativos para *{$client->name}*.\n\nFale com a corretora se acredita que isso é um erro.",
                self::COMMAND_BALANCE,
            );

            return;
        }

        $today = Carbon::today();
        $lines = [];

        foreach ($sales as $sale) {
            $pending = $sale->installments
                ->filter(fn (Installment $i): bool => $i->status !== Installment::STATUS_PAID)
                ->sortBy('due_date');

            if ($pending->isEmpty()) {
                continue;
            }

            $contractNo = $this->contractNumber($sale);
            $lines[] = "*Contrato {$contractNo}* · Q{$sale->lot?->block} · L{$sale->lot?->number}";

            foreach ($pending->take(8) as $installment) {
                $label = $this->installmentLabel($installment);
                $status = $installment->displayStatus();
                $statusLabel = match ($status) {
                    Installment::STATUS_OVERDUE => 'ATRASADA',
                    Installment::STATUS_PAID => 'PAGA',
                    default => 'pendente',
                };
                $due = $installment->due_date?->format('d/m/Y') ?? '—';
                $value = $this->formatMoney((int) $installment->value);

                $lines[] = "• {$label} — {$value} — venc. {$due} ({$statusLabel})";
            }

            if ($pending->count() > 8) {
                $lines[] = '_… e mais '.($pending->count() - 8).' parcela(s)_';
            }

            $lines[] = '';
        }

        if ($lines === []) {
            $this->sendBotResponse(
                $client,
                $phone,
                "✅ *{$client->name}*, todas as parcelas estão em dia. Obrigado!",
                self::COMMAND_BALANCE,
            );

            return;
        }

        $message = implode("\n", array_merge(
            ["📋 *Saldo de {$client->name}* ({$today->format('d/m/Y')})", ''],
            $lines,
            [
                'Para pagar agora, digite *2ª via*.',
                "Portal: {$this->portalUrl()}",
            ],
        ));

        $this->sendBotResponse($client, $phone, $message, self::COMMAND_BALANCE);
    }

    private function handleStatement(Client $client, string $phone): void
    {
        $sales = $this->activeSales($client);

        if ($sales->isEmpty()) {
            $this->sendBotResponse(
                $client,
                $phone,
                "Não encontramos contratos ativos para *{$client->name}*.",
                self::COMMAND_STATEMENT,
            );

            return;
        }

        $lines = ["📄 *Extrato — {$client->name}*", ''];

        foreach ($sales as $sale) {
            $paid = $sale->installments
                ->filter(fn (Installment $i): bool => $i->status === Installment::STATUS_PAID)
                ->sortByDesc(fn (Installment $i) => $i->paid_at?->timestamp ?? 0);

            $contractNo = $this->contractNumber($sale);
            $lines[] = "*Contrato {$contractNo}*";

            if ($paid->isEmpty()) {
                $lines[] = '_Nenhum pagamento registrado ainda._';
                $lines[] = '';

                continue;
            }

            foreach ($paid->take(10) as $installment) {
                $label = $this->installmentLabel($installment);
                $paidAt = $installment->paid_at?->format('d/m/Y') ?? '—';
                $value = $this->formatMoney((int) $installment->value);
                $lines[] = "• {$label} — {$value} — pago em {$paidAt}";
            }

            if ($paid->count() > 10) {
                $lines[] = '_… e mais '.($paid->count() - 10).' pagamento(s)_';
            }

            $lines[] = '';
        }

        $lines[] = "Extrato completo: {$this->portalUrl()}";

        $this->sendBotResponse($client, $phone, implode("\n", $lines), self::COMMAND_STATEMENT);
    }

    private function handleContract(Client $client, string $phone, ?string $argument): void
    {
        $sales = $this->activeSales($client);

        if ($sales->isEmpty()) {
            $this->sendBotResponse(
                $client,
                $phone,
                "Não encontramos contratos ativos para *{$client->name}*.",
                self::COMMAND_CONTRACT,
            );

            return;
        }

        $sale = $this->resolveSaleFromArgument($sales, $argument);

        if ($sale === null) {
            $list = $sales->map(fn (Sale $s): string => '• *contrato '.$this->contractNumber($s).'* — Q'.$s->lot?->block.' · L'.$s->lot?->number)
                ->implode("\n");

            $this->sendBotResponse(
                $client,
                $phone,
                "Você possui mais de um contrato.\n\nEnvie, por exemplo:\n*contrato 0001/2025*\n\nContratos:\n{$list}",
                self::COMMAND_CONTRACT,
            );

            return;
        }

        try {
            $pdfBytes = $this->generateContract->execute($sale);
        } catch (\Throwable $e) {
            Log::error('WhatsappBotService: contract PDF failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            $this->sendBotResponse(
                $client,
                $phone,
                'Não foi possível gerar o contrato agora. Digite *atendimento* para falar com o corretor.',
                self::COMMAND_CONTRACT,
                saleId: $sale->id,
            );

            return;
        }

        $contractNo = $this->contractNumber($sale);
        $filename = "contrato-{$sale->id}.pdf";
        $caption = "Contrato {$contractNo} — Sid360 Imóveis";

        $sent = $this->whatsapp->sendDocument(
            phone: $phone,
            filename: $filename,
            caption: $caption,
            base64File: base64_encode($pdfBytes),
        );

        InstallmentInteraction::create([
            'sale_id' => $sale->id,
            'client_id' => $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_CONTRACT,
            'message' => $caption,
            'meta' => [
                'sent' => $sent,
                'command' => self::COMMAND_CONTRACT,
                'filename' => $filename,
            ],
        ]);

        if (! $sent) {
            $this->sendBotResponse(
                $client,
                $phone,
                "Não foi possível enviar o PDF agora.\n\nAcesse o portal ou digite *atendimento*.",
                self::COMMAND_CONTRACT,
                saleId: $sale->id,
            );
        }
    }

    private function handleSupport(Client $client, string $phone): void
    {
        $sales = $this->activeSales($client);
        $sale = $sales->first();
        $sidDisplay = $this->sidPhoneDisplay();

        $message = "📞 *{$client->name}*, o corretor Sid foi notificado e entrará em contato em breve.\n\nOu chame diretamente:\n📱 *{$this->sidWaMeLink()}*\n\n_Sid360 Imóveis · {$sidDisplay}_";

        $this->sendBotResponse($client, $phone, $message, self::COMMAND_SUPPORT, saleId: $sale?->id);

        $contractInfo = $sale
            ? 'Contrato: '.$this->contractNumber($sale)."\nLote: Q{$sale->lot?->block} · L{$sale->lot?->number}\n"
            : '';

        $this->whatsapp->send(
            $this->sidPhoneDigits(),
            "🤝 *{$client->name}* solicitou atendimento via bot.\n{$contractInfo}Fone: {$client->phone}\n\n⚡ Responda logo!",
        );

        InstallmentInteraction::create([
            'sale_id' => $sale?->id,
            'client_id' => $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_SUPPORT_NOTIFY,
            'message' => 'Notificação enviada ao corretor',
            'meta' => ['sent' => true],
        ]);
    }

    /**
     * @return Collection<int, Sale>
     */
    private function activeSales(Client $client): Collection
    {
        return Sale::query()
            ->where('status', '!=', Sale::STATUS_CANCELLED)
            ->where(function (Builder $query) use ($client): void {
                $query->where('client_id', $client->id)
                    ->orWhereHas('buyers', fn (Builder $buyerQuery) => $buyerQuery->where('clients.id', $client->id));
            })
            ->with(['lot.development', 'installments'])
            ->orderByDesc('sale_date')
            ->get();
    }

    private function nextPayableInstallment(Client $client): ?Installment
    {
        $sales = $this->activeSales($client);

        $candidates = $sales
            ->flatMap(fn (Sale $sale) => $sale->installments)
            ->filter(fn (Installment $i): bool => $i->status !== Installment::STATUS_PAID)
            ->sortBy(fn (Installment $i): array => [
                $i->isOverdue() ? 0 : 1,
                $i->due_date?->timestamp ?? PHP_INT_MAX,
                $i->number,
            ])
            ->values();

        return $candidates->first();
    }

    /**
     * @param  Collection<int, Sale>  $sales
     */
    private function resolveSaleFromArgument(Collection $sales, ?string $argument): ?Sale
    {
        if ($sales->count() === 1) {
            return $sales->first();
        }

        if ($argument === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $argument) ?? '';

        if ($digits === '') {
            return null;
        }

        if (preg_match('/^(\d{1,4})(\d{4})$/', $digits, $matches)) {
            $saleId = (int) $matches[1];
            $year = $matches[2];

            return $sales->first(function (Sale $sale) use ($saleId, $year): bool {
                return $sale->id === $saleId
                    && $sale->sale_date?->format('Y') === $year;
            });
        }

        $saleId = (int) ltrim($digits, '0');

        return $sales->first(fn (Sale $sale): bool => $sale->id === $saleId);
    }

    private function installmentLabel(Installment $installment): string
    {
        if ($installment->type === Installment::TYPE_DOWN_PAYMENT) {
            return 'Entrada';
        }

        return 'Parcela '.str_pad((string) $installment->number, 2, '0', STR_PAD_LEFT);
    }

    private function contractNumber(Sale $sale): string
    {
        return str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.($sale->sale_date?->format('Y') ?? now()->format('Y'));
    }

    private function formatMoney(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    private function portalUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/pagamentos';
    }

    private function sidPhoneDigits(): string
    {
        $digits = preg_replace('/\D/', '', (string) Setting::get('whatsapp_sid_phone', '5574988230151')) ?? '';

        return $digits !== '' ? $digits : '5574988230151';
    }

    private function sidWaMeLink(): string
    {
        return 'wa.me/'.$this->sidPhoneDigits();
    }

    private function sidPhoneDisplay(): string
    {
        $digits = $this->sidPhoneDigits();

        if (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            return sprintf(
                '(%s) %s %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 1),
                substr($digits, 3, 4),
                substr($digits, 7, 4),
            );
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordInbound(
        Client $client,
        string $phone,
        string $body,
        string $command,
        array $payload,
        ?string $argument = null,
    ): void {
        InstallmentInteraction::create([
            'client_id' => $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_COMMAND,
            'message' => $body,
            'meta' => [
                'command' => $command,
                'argument' => $argument,
                'from' => $payload['from'] ?? null,
            ],
        ]);
    }

    private function sendBotResponse(
        Client $client,
        string $phone,
        string $message,
        string $command,
        ?int $saleId = null,
        ?int $installmentId = null,
    ): void {
        $this->whatsapp->sendAndRecord(
            phone: $phone,
            message: $message,
            type: InstallmentInteraction::TYPE_BOT_RESPONSE,
            installmentId: $installmentId,
            saleId: $saleId,
            clientId: $client->id,
            meta: ['command' => $command],
        );
    }
}
