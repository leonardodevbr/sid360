<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Installment\SendInstallmentBoletoWhatsappAction;
use App\Actions\Installment\SendInstallmentPixWhatsappAction;
use App\Actions\Sale\SendSaleCarneWhatsappAction;
use App\Actions\Sale\SendSaleContractWhatsappAction;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\WhatsappConversationState;
use App\Support\WhatsappBotMessageFooter;
use App\Support\WhatsappCommandParser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WhatsappBotService
{
    public const COMMAND_MENU = WhatsappCommandParser::COMMAND_MENU;

    public const COMMAND_PAYMENT = WhatsappCommandParser::COMMAND_PAYMENT;

    public const COMMAND_BALANCE = WhatsappCommandParser::COMMAND_BALANCE;

    public const COMMAND_STATEMENT = WhatsappCommandParser::COMMAND_STATEMENT;

    public const COMMAND_CONTRACT = WhatsappCommandParser::COMMAND_CONTRACT;

    public const COMMAND_CARNE = WhatsappCommandParser::COMMAND_CARNE;

    public const COMMAND_SUPPORT = WhatsappCommandParser::COMMAND_SUPPORT;

    public const COMMAND_PAUSE = WhatsappCommandParser::COMMAND_PAUSE;

    public const COMMAND_RESUME = WhatsappCommandParser::COMMAND_RESUME;

    public const COMMAND_HUMAN = WhatsappCommandParser::COMMAND_HUMAN;

    public const COMMAND_UNKNOWN = WhatsappCommandParser::COMMAND_UNKNOWN;

    public function __construct(
        private readonly WhatsappService $whatsapp,
        private readonly WhatsappCommandParser $commandParser,
        private readonly WhatsappConversationStateService $conversationState,
        private readonly SendInstallmentPixWhatsappAction $sendPix,
        private readonly SendInstallmentBoletoWhatsappAction $sendBoleto,
        private readonly SendSaleContractWhatsappAction $sendContract,
        private readonly SendSaleCarneWhatsappAction $sendCarne,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        Client $client,
        string $phone,
        string $body,
        array $payload = [],
        ?WhatsappConversationState $state = null,
        ?string $forcedCommand = null,
    ): void {
        if (! Setting::get('whatsapp_bot_enabled', true)) {
            return;
        }

        $state ??= $this->conversationState->findOrCreate($phone, $client->id);

        if (! $client->acceptsWhatsappNotifications()) {
            $this->recordInboundCommand($client, $phone, $body, self::COMMAND_UNKNOWN, $payload);
            $this->sendBotResponse(
                $client,
                $phone,
                'Este número está cadastrado para não receber mensagens automáticas. Fale com a corretora para reativar.',
                self::COMMAND_UNKNOWN,
                state: $state,
            );

            return;
        }

        [$command, $argument] = $forcedCommand !== null
            ? [$forcedCommand, null]
            : $this->parseCommand($body);

        $this->recordInboundCommand($client, $phone, $body, $command, $payload, $argument);

        match ($command) {
            self::COMMAND_MENU, self::COMMAND_RESUME => $this->sendMenu($client, $phone, state: $state),
            self::COMMAND_PAYMENT => $this->handlePayment($client, $phone, $state),
            self::COMMAND_BALANCE => $this->handleBalance($client, $phone, $state),
            self::COMMAND_STATEMENT => $this->handleStatement($client, $phone, $state),
            self::COMMAND_CONTRACT => $this->handleContract($client, $phone, $argument, $state),
            self::COMMAND_CARNE => $this->handleCarne($client, $phone, $argument, $state),
            self::COMMAND_SUPPORT, self::COMMAND_HUMAN => $this->handleSupport($client, $phone, $state, $command),
            default => $this->sendMenu($client, $phone, unknown: true, state: $state),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordIgnoredInbound(
        Client $client,
        string $phone,
        string $body,
        WhatsappConversationState $state,
        array $payload,
        string $command,
    ): void {
        InstallmentInteraction::query()->create([
            'client_id' => $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_IGNORED,
            'message' => $body,
            'meta' => [
                'command' => $command,
                'conversation_status' => $state->status,
                'human_until' => $state->human_until?->toIso8601String(),
                'from' => $payload['from'] ?? null,
                'ignored' => true,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordInboundCommand(
        Client $client,
        string $phone,
        string $body,
        string $command,
        array $payload,
        ?string $argument = null,
    ): void {
        InstallmentInteraction::query()->create([
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

    /**
     * @return array{0: string, 1: string|null}
     */
    public function parseCommand(string $body): array
    {
        return $this->commandParser->parse($body);
    }

    private function sendMenu(
        Client $client,
        string $phone,
        bool $unknown = false,
        ?WhatsappConversationState $state = null,
    ): void {
        $template = (string) Setting::get(
            'whatsapp_bot_menu_message',
            "Olá, *{nome}*! Sou o assistente *Sid360*.\n\nDigite um comando:\n\n*2ª via* — receber PIX ou boleto\n*saldo* — parcelas pendentes\n*extrato* — histórico de pagamentos\n*contrato* — PDF do contrato\n*carne* — carnê / promissória\n*atendimento* — falar com o corretor\n\nPortal: {portal_url}",
        );

        $prefix = $unknown
            ? "Não entendi sua mensagem.\n\n"
            : '';

        $message = $prefix.$this->whatsapp->interpolate($template, [
            'nome' => $client->name,
            'portal_url' => $this->portalUrl(),
        ]);

        $this->sendBotResponse($client, $phone, $message, self::COMMAND_MENU, state: $state);
    }

    private function handlePayment(Client $client, string $phone, WhatsappConversationState $state): void
    {
        $installment = $this->nextPayableInstallment($client);

        if ($installment === null) {
            $this->sendBotResponse(
                $client,
                $phone,
                "✅ *{$client->name}*, não encontramos parcelas em aberto nos seus contratos.\n\nPortal: {$this->portalUrl()}",
                self::COMMAND_PAYMENT,
                state: $state,
            );

            return;
        }

        $pixSent = $this->sendPix->execute(
            installment: $installment,
            phone: $phone,
            interactionType: InstallmentInteraction::TYPE_BOT_PAYMENT,
        );

        $boleto = $this->sendBoleto->execute(
            installment: $installment,
            phone: $phone,
            interactionType: InstallmentInteraction::TYPE_BOT_PAYMENT,
        );

        if ($pixSent || ($boleto['ok'] ?? false)) {
            return;
        }

        $this->sendBotResponse(
            client: $client,
            phone: $phone,
            message: $this->paymentFailureMessage($client, $boleto['error'] ?? null),
            command: self::COMMAND_PAYMENT,
            saleId: $installment->sale_id,
            installmentId: $installment->id,
            state: $state,
        );
    }

    private function paymentFailureMessage(Client $client, ?string $boletoError): string
    {
        $portalUrl = $this->portalUrl();

        if ($boletoError !== null && (
            str_contains(mb_strtolower($boletoError), 'limite')
            || str_contains(mb_strtolower($boletoError), 'máximo')
        )) {
            return "⚠️ *{$client->name}*, o boleto não pôde ser gerado:\n{$boletoError}\n\n"
                ."O PIX pode ter sido enviado acima (se disponível).\n\n"
                ."Portal: {$portalUrl}\n\nOu digite *atendimento*.";
        }

        return "Não foi possível gerar o pagamento agora.\n\nAcesse o portal:\n🔗 {$portalUrl}\n\nOu digite *atendimento* para falar com o corretor.";
    }

    private function handleBalance(Client $client, string $phone, WhatsappConversationState $state): void
    {
        $sales = $this->activeSales($client);

        if ($sales->isEmpty()) {
            $this->sendBotResponse(
                $client,
                $phone,
                "Não encontramos contratos ativos para *{$client->name}*.\n\nFale com a corretora se acredita que isso é um erro.",
                self::COMMAND_BALANCE,
                state: $state,
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
                state: $state,
            );

            return;
        }

        $message = implode("\n", array_merge(
            ["📋 *Saldo de {$client->name}* ({$today->format('d/m/Y')})", ''],
            $lines,
            [
                'Para pagar agora, digite *2ª via*, *quero pagar* ou *manda o pix*.',
                "Portal: {$this->portalUrl()}",
            ],
        ));

        $this->sendBotResponse($client, $phone, $message, self::COMMAND_BALANCE, state: $state);
    }

    private function handleStatement(Client $client, string $phone, WhatsappConversationState $state): void
    {
        $sales = $this->activeSales($client);

        if ($sales->isEmpty()) {
            $this->sendBotResponse(
                $client,
                $phone,
                "Não encontramos contratos ativos para *{$client->name}*.",
                self::COMMAND_STATEMENT,
                state: $state,
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

        $this->sendBotResponse($client, $phone, implode("\n", $lines), self::COMMAND_STATEMENT, state: $state);
    }

    private function handleContract(
        Client $client,
        string $phone,
        ?string $argument,
        WhatsappConversationState $state,
    ): void {
        $this->sendSaleDocument(
            client: $client,
            phone: $phone,
            argument: $argument,
            command: self::COMMAND_CONTRACT,
            interactionType: InstallmentInteraction::TYPE_BOT_CONTRACT,
            sendAction: fn (Sale $sale): bool => $this->sendContract->execute(
                sale: $sale,
                phone: $phone,
                interactionType: InstallmentInteraction::TYPE_BOT_CONTRACT,
            ),
            documentLabel: 'contrato',
            failureMessage: "Não foi possível enviar o PDF do contrato pelo WhatsApp.\n\n"
                ."Peça ao corretor pelo comando *atendimento* ou acesse:\n{$this->portalUrl()}",
            state: $state,
        );
    }

    private function handleCarne(
        Client $client,
        string $phone,
        ?string $argument,
        WhatsappConversationState $state,
    ): void {
        $this->sendSaleDocument(
            client: $client,
            phone: $phone,
            argument: $argument,
            command: self::COMMAND_CARNE,
            interactionType: InstallmentInteraction::TYPE_BOT_CARNE,
            sendAction: fn (Sale $sale): bool => $this->sendCarne->execute(
                sale: $sale,
                phone: $phone,
                interactionType: InstallmentInteraction::TYPE_BOT_CARNE,
            ),
            documentLabel: 'carnê',
            failureMessage: "Não foi possível enviar o carnê pelo WhatsApp.\n\n"
                ."Peça ao corretor pelo comando *atendimento* ou acesse:\n{$this->portalUrl()}",
            state: $state,
        );
    }

    /**
     * @param  callable(Sale): bool  $sendAction
     */
    private function sendSaleDocument(
        Client $client,
        string $phone,
        ?string $argument,
        string $command,
        string $interactionType,
        callable $sendAction,
        string $documentLabel,
        string $failureMessage,
        WhatsappConversationState $state,
    ): void {
        $sales = $this->activeSales($client);

        if ($sales->isEmpty()) {
            $this->sendBotResponse(
                $client,
                $phone,
                "Não encontramos contratos ativos para *{$client->name}*.",
                $command,
                state: $state,
            );

            return;
        }

        $sale = $this->resolveSaleFromArgument($sales, $argument);

        if ($sale === null) {
            $commandLabel = match ($command) {
                self::COMMAND_CONTRACT => 'contrato',
                self::COMMAND_CARNE => 'carne',
                default => $command,
            };

            $list = $sales->map(fn (Sale $s): string => '• *'.$commandLabel.' '.$this->contractNumber($s).'* — Q'.$s->lot?->block.' · L'.$s->lot?->number)
                ->implode("\n");

            $this->sendBotResponse(
                $client,
                $phone,
                "Você possui mais de um contrato.\n\nEnvie, por exemplo:\n*{$commandLabel} 0001/2025*\n\nContratos:\n{$list}",
                $command,
                state: $state,
            );

            return;
        }

        $contractNo = $this->contractNumber($sale);

        $this->whatsapp->sendAndRecord(
            phone: $phone,
            message: "Olá, *{$client->name}*! Segue o PDF do *{$documentLabel}* do contrato *{$contractNo}*.",
            type: InstallmentInteraction::TYPE_BOT_RESPONSE,
            saleId: $sale->id,
            clientId: $client->id,
            meta: ['command' => $command, 'step' => 'document_intro'],
            wppconnectOptions: WhatsappBotMessageFooter::wppconnectOptions(),
        );

        $this->conversationState->touchOutbound($state);

        $sent = $sendAction($sale);

        InstallmentInteraction::create([
            'sale_id' => $sale->id,
            'client_id' => $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => $interactionType,
            'message' => ucfirst($documentLabel)." {$contractNo}",
            'meta' => [
                'sent' => $sent,
                'command' => $command,
            ],
        ]);

        if (! $sent) {
            $this->sendBotResponse(
                $client,
                $phone,
                $failureMessage,
                $command,
                saleId: $sale->id,
                state: $state,
            );
        }
    }

    private function handleSupport(
        Client $client,
        string $phone,
        WhatsappConversationState $state,
        string $command = self::COMMAND_SUPPORT,
    ): void {
        $sales = $this->activeSales($client);
        $sale = $sales->first();
        $sidDisplay = $this->sidPhoneDisplay();

        $message = "📞 *{$client->name}*, o corretor Sid foi notificado e entrará em contato em breve.\n\n"
            ."Nas próximas 24 horas, o assistente automático ficará pausado.\n\n"
            ."Ou chame diretamente:\n📱 *{$this->sidWaMeLink()}*\n\n_Sid360 Imóveis · {$sidDisplay}_";

        $this->sendBotResponse($client, $phone, $message, $command, saleId: $sale?->id, state: $state);

        $contractInfo = $sale
            ? 'Contrato: '.$this->contractNumber($sale)."\nLote: Q{$sale->lot?->block} · L{$sale->lot?->number}\n"
            : '';

        $this->whatsapp->notifySid(
            message: "🤝 *{$client->name}* solicitou atendimento via bot.\n{$contractInfo}Fone: {$client->phone}\n\n⚡ Responda logo!",
            saleId: $sale?->id,
            clientId: $client->id,
            relatedClientPhone: (string) $client->phone,
            type: InstallmentInteraction::TYPE_BOT_SUPPORT_NOTIFY,
        );
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

    private function sidWaMeLink(): string
    {
        return 'wa.me/'.$this->whatsapp->sidPhoneDigits();
    }

    private function sidPhoneDigits(): string
    {
        return $this->whatsapp->sidPhoneDigits();
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

    private function sendBotResponse(
        Client $client,
        string $phone,
        string $message,
        string $command,
        ?int $saleId = null,
        ?int $installmentId = null,
        ?WhatsappConversationState $state = null,
    ): void {
        $state ??= $this->conversationState->findOrCreate($phone, $client->id);

        $this->whatsapp->sendAndRecord(
            phone: $phone,
            message: $message,
            type: InstallmentInteraction::TYPE_BOT_RESPONSE,
            installmentId: $installmentId,
            saleId: $saleId,
            clientId: $client->id,
            meta: ['command' => $command],
            wppconnectOptions: WhatsappBotMessageFooter::wppconnectOptions(),
        );

        $this->conversationState->touchOutbound($state);
    }
}
