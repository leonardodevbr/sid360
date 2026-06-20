<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Installment\SendInstallmentBoletoWhatsappAction;
use App\Actions\Installment\SendInstallmentPixWhatsappAction;
use App\Actions\Whatsapp\ProcessWhatsappBotMessageAction;
use App\Support\WhatsappBotContinuationButtons;
use App\Support\WhatsappBotMenuButtons;
use App\Support\WhatsappReminderButtons;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsappService $whatsapp,
        private readonly SendInstallmentPixWhatsappAction $sendPixWhatsapp,
        private readonly SendInstallmentBoletoWhatsappAction $sendBoletoWhatsapp,
        private readonly ProcessWhatsappBotMessageAction $processBotMessage,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            return $this->process($request);
        } catch (\Throwable $e) {
            // Qualquer exceção não tratada aqui derrubava a resposta pro
            // WPPConnect com HTTP 500 — o servidor via isso como "falha do
            // webhook" mesmo quando a mensagem já tinha sido recebida e
            // logada normalmente. Capturamos, logamos com stack trace
            // completo (pra diagnosticar a causa real) e devolvemos 200,
            // pra não marcar o webhook como instável por um erro que não
            // afeta a entrega da mensagem em si.
            Log::error('WhatsApp webhook: exceção não tratada', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => true]);
        }
    }

    private function process(Request $request): JsonResponse
    {
        $payload = $this->resolvePayload($request);
        $event = strtolower(trim((string) ($payload['event'] ?? $request->input('event', ''))));

        if ($event !== '' && ! in_array($event, ['onmessage', 'message'], true)) {
            return response()->json(['ok' => true]);
        }

        if ($this->isFromMe($request, $payload)) {
            return response()->json(['ok' => true]);
        }

        $from = $this->resolveFrom($payload);
        $body = $this->resolveMessageBody($payload);

        Log::info('WhatsApp webhook inbound', [
            'event' => $event,
            'from' => $from,
            'body' => $body,
            'type' => $payload['type'] ?? null,
            // Sessão WPPConnect de origem (quando o payload traz esse campo) —
            // sem isso não dá pra saber qual das sessões conectadas (ex.:
            // "sid360" vs "leonardo-teste") gerou cada chamada, já que as duas
            // apontam pra mesma URL de webhook.
            'session' => data_get($payload, 'session') ?? data_get($payload, 'session.id') ?? null,
        ]);

        if ($from === null || $from === '') {
            return response()->json(['ok' => true]);
        }

        if (str_contains($from, '@g.us') || str_contains($from, '@broadcast')) {
            return response()->json(['ok' => true]);
        }

        if (str_ends_with($from, '@lid')) {
            // WhatsApp pode entregar o contato como um "LID" (identificador
            // opaco de privacidade) em vez do JID com o número de telefone
            // real (@c.us). Quando isso acontece, FindClientByWhatsappPhoneAction
            // nunca vai conseguir casar esse "from" com o telefone cadastrado
            // no Client — o contato sempre vai cair em "unknown contact",
            // mesmo sendo um cliente real. Logamos os campos candidatos que
            // às vezes trazem o número de telefone verdadeiro em paralelo ao
            // LID, pra confirmar se algum deles está disponível no payload do
            // WPPConnect e dá pra usar como fallback de resolução.
            Log::warning('WhatsApp webhook: contato via @lid (sem telefone direto)', [
                'from' => $from,
                'candidatos' => array_filter([
                    'author' => data_get($payload, 'author'),
                    'participant' => data_get($payload, 'participant'),
                    'senderPn' => data_get($payload, 'senderPn'),
                    'sender_pn' => data_get($payload, 'sender_pn'),
                    'sender.id' => data_get($payload, 'sender.id'),
                    'sender.id._serialized' => data_get($payload, 'sender.id._serialized'),
                    'sender.pushname' => data_get($payload, 'sender.pushname'),
                    'notifyName' => data_get($payload, 'notifyName'),
                    'chatId' => data_get($payload, 'chatId'),
                ], fn ($v) => $v !== null),
            ]);
        }

        $option = $this->extractOption($payload, $body);
        $windowHours = (int) Setting::get('whatsapp_reply_window_hours', 48);
        $since = Carbon::now()->subHours($windowHours);

        if ($option !== '' && WhatsappBotContinuationButtons::isContinuationButtonId($option)) {
            $continuationContext = $this->findLastBotContinuationOutbound($payload, $from, $since);

            if ($continuationContext) {
                $commandBody = WhatsappBotContinuationButtons::commandBodyFromButtonId($option) ?? $option;
                $this->processBotMessage->execute($from, $commandBody, $payload);

                return response()->json(['ok' => true]);
            }
        }

        if ($option !== '' && WhatsappReminderButtons::isReminderButtonId($option)) {
            $reminderContext = $this->findLastReminderOutbound($payload, $from, $since);

            if ($reminderContext) {
                $this->processReminderButtonReply($payload, $from, $option, $reminderContext);

                return response()->json(['ok' => true]);
            }
        }

        if ($option !== '' && WhatsappBotMenuButtons::isBotMenuRowId($option)) {
            $botMenuContext = $this->findLastBotMenuOutbound($payload, $from, $since);

            if ($botMenuContext) {
                $commandBody = WhatsappBotMenuButtons::commandBodyFromRowId($option) ?? $option;
                $this->processBotMessage->execute($from, $commandBody, $payload);

                return response()->json(['ok' => true]);
            }
        }

        if ($option !== '' && in_array($option, ['1', '2', '3'], true)) {
            $lastOutbound = $this->findLastOutbound($payload, $from, $since);

            if ($lastOutbound) {
                $this->processReply($payload, $from, $option, $lastOutbound);

                return response()->json(['ok' => true]);
            }
        }

        if ($body !== '') {
            $continuationButton = WhatsappBotContinuationButtons::buttonIdFromBody($body);

            if ($continuationButton !== null) {
                $continuationContext = $this->findLastBotContinuationOutbound($payload, $from, $since);

                if ($continuationContext) {
                    $commandBody = WhatsappBotContinuationButtons::commandBodyFromButtonId($continuationButton) ?? $body;
                    $this->processBotMessage->execute($from, $commandBody, $payload);

                    return response()->json(['ok' => true]);
                }
            }

            $reminderButton = WhatsappReminderButtons::buttonIdFromBody($body);

            if ($reminderButton !== null) {
                $reminderContext = $this->findLastReminderOutbound($payload, $from, $since);

                if ($reminderContext) {
                    $this->processReminderButtonReply($payload, $from, $reminderButton, $reminderContext);

                    return response()->json(['ok' => true]);
                }
            }

            $mapped = $this->mapBodyToOption($body);

            if ($mapped !== $body && in_array($mapped, ['1', '2', '3'], true)) {
                $lastOutbound = $this->findLastOutbound($payload, $from, $since);

                if ($lastOutbound) {
                    $this->processReply($payload, $from, $mapped, $lastOutbound);

                    return response()->json(['ok' => true]);
                }
            }

            $this->processBotMessage->execute($from, $body, $payload);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * WPPConnect pode enviar o payload na raiz ou dentro de "data".
     *
     * @return array<string, mixed>
     */
    private function resolvePayload(Request $request): array
    {
        $root = $request->all();
        $nested = is_array($root['data'] ?? null) ? $root['data'] : [];
        $message = is_array($nested['message'] ?? null)
            ? $nested['message']
            : (is_array($root['message'] ?? null) ? $root['message'] : []);

        return array_merge($root, $nested, $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isFromMe(Request $request, array $payload): bool
    {
        if ($request->boolean('fromMe')) {
            return true;
        }

        $fromMe = $payload['fromMe'] ?? false;

        return filter_var($fromMe, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveFrom(array $payload): ?string
    {
        foreach ([
            data_get($payload, 'from'),
            data_get($payload, 'chatId'),
            data_get($payload, 'sender.id._serialized'),
            data_get($payload, 'sender.id'),
            data_get($payload, 'author'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Tipos de mensagem do WPPConnect cujo campo "body" pode vir com o
     * conteúdo bruto (geralmente base64) da mídia em vez de texto — um
     * áudio (ptt) de poucos segundos já passa de 65KB em base64 e quebra a
     * coluna TEXT de installment_interactions.message (erro 1406 "Data too
     * long for column" visto em produção). Pra esses tipos ignoramos
     * "body"/"content"/"text" e usamos só a legenda (quando houver) ou um
     * placeholder curto.
     *
     * @var string[]
     */
    private const MEDIA_MESSAGE_TYPES = [
        'ptt', 'audio', 'image', 'video', 'document', 'sticker',
        'location', 'vcard', 'multi_vcard',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveMessageBody(array $payload): string
    {
        $type = (string) (data_get($payload, 'type') ?? 'chat');

        if (in_array($type, self::MEDIA_MESSAGE_TYPES, true)) {
            $caption = data_get($payload, 'caption');

            if (is_string($caption) && trim($caption) !== '') {
                return $this->truncateMessage(trim($caption));
            }

            return "[{$type}]";
        }

        foreach ([
            data_get($payload, 'body'),
            data_get($payload, 'content'),
            data_get($payload, 'text'),
            data_get($payload, 'caption'),
            data_get($payload, 'listResponse.title'),
            data_get($payload, 'selectedDisplayText'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $this->truncateMessage(trim($candidate));
            }
        }

        return '';
    }

    /**
     * Trava de segurança: nada que vier do payload do WPPConnect deve ser
     * gravado em installment_interactions.message além do limite da coluna
     * TEXT (65.535 bytes). 4000 caracteres é muito mais que qualquer
     * mensagem de texto real e ainda fica bem longe do limite mesmo
     * considerando caracteres multibyte.
     */
    private function truncateMessage(string $value, int $limit = 4000): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'…' : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOption(array $payload, string $body): string
    {
        $rowId = data_get($payload, 'listResponse.singleSelectReply.selectedRowId')
            ?? data_get($payload, 'selectedRowId');

        if (is_string($rowId) && $rowId !== '') {
            return trim($rowId);
        }

        $buttonId = data_get($payload, 'selectedButtonId')
            ?? data_get($payload, 'selectedButtonID');

        if (is_string($buttonId) && $buttonId !== '') {
            return trim($buttonId);
        }

        if ($body === '') {
            return '';
        }

        if (preg_match('/^[1-3]$/', $body)) {
            return $body;
        }

        return $this->mapBodyToOption($body);
    }

    private function mapBodyToOption(string $body): string
    {
        $lower = mb_strtolower($body);

        if (str_contains($lower, 'regularizar') || str_contains($lower, 'ciência') || str_contains($lower, 'ciencia')) {
            return '1';
        }

        if (str_contains($lower, 'pix') || str_contains($lower, 'boleto')) {
            return '2';
        }

        if (str_contains($lower, 'negociar') || str_contains($lower, 'corretor')) {
            return '3';
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processReply(
        array $payload,
        string $from,
        string $option,
        InstallmentInteraction $lastOutbound,
    ): void {
        $sale = $lastOutbound->sale;
        $client = $sale?->client;

        if (! $sale || ! $client) {
            return;
        }

        if (! $client->acceptsWhatsappNotifications()) {
            return;
        }

        $bodyText = $this->truncateMessage(trim((string) ($payload['body'] ?? $payload['content'] ?? $option)));
        $phone = preg_replace('/[^0-9]/', '', $from) ?? $from;
        $sidWaMe = 'wa.me/'.$this->whatsapp->sidPhoneDigits();
        $sidDisplay = $this->formatSidPhoneDisplay();

        if ($option === '2') {
            $this->processPaymentReply($payload, $from, $lastOutbound, $sale, $client, $bodyText, $phone);

            return;
        }

        [$type, $replyMessage, $sidNotification] = match ($option) {
            '1' => [
                InstallmentInteraction::TYPE_REPLY_ACKNOWLEDGE,
                "✅ Olá, *{$client->name}*! Recebemos sua confirmação.\n\nFique tranquilo(a), assim que o pagamento for realizado atualizaremos seu cadastro.\n\nQualquer dúvida: {$sidDisplay}\n_Sid360 Imóveis_",
                "📬 *{$client->name}* confirmou que vai regularizar o contrato ".
                str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y').".\n".
                "Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}\nFone: {$client->phone}",
            ],
            '3' => [
                InstallmentInteraction::TYPE_REPLY_NEGOTIATE,
                "📞 Olá, *{$client->name}*! O corretor Sid foi notificado e entrará em contato em breve.\n\nOu se preferir, chame diretamente:\n📱 *{$sidWaMe}*\n_Sid360 Imóveis_",
                "🤝 *{$client->name}* quer negociar.\n".
                'Contrato: '.str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y')."\n".
                "Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}\nFone: {$client->phone}\n\n⚡ Responda logo!",
            ],
            default => [
                InstallmentInteraction::TYPE_REPLY_UNKNOWN,
                "Olá! Não entendi sua resposta. Por favor responda com:\n\n*1* - Estou ciente, vou regularizar\n*2* - Quero link de pagamento\n*3* - Preciso negociar\n\nOu fale com a gente: {$sidDisplay}",
                null,
            ],
        };

        $installmentId = $lastOutbound->installment_id !== null
            ? (int) $lastOutbound->installment_id
            : null;

        InstallmentInteraction::create([
            'installment_id' => $installmentId,
            'sale_id' => (int) $sale->id,
            'client_id' => (int) $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => $type,
            'message' => $bodyText,
            'meta' => [
                'option' => $option,
                'from' => $from,
                'message_type' => $payload['type'] ?? null,
            ],
        ]);

        Log::info('WhatsApp webhook: processing reply', [
            'sale_id' => $sale->id,
            'option' => $option,
            'type' => $type,
        ]);

        $this->whatsapp->sendAndRecord(
            phone: $client->phone,
            message: $replyMessage,
            type: $type.'_response',
            installmentId: $installmentId,
            saleId: (int) $sale->id,
            clientId: (int) $client->id,
        );

        if ($sidNotification) {
            $this->whatsapp->notifySid(
                message: $sidNotification,
                saleId: (int) $sale->id,
                clientId: (int) $client->id,
                relatedClientPhone: (string) $client->phone,
                type: InstallmentInteraction::TYPE_SID_NOTIFY,
                meta: ['trigger' => $type, 'option' => $option],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processPaymentReply(
        array $payload,
        string $from,
        InstallmentInteraction $lastOutbound,
        Sale $sale,
        Client $client,
        string $bodyText,
        string $phone,
    ): void {
        $installment = $this->resolvePaymentInstallment($sale, $lastOutbound);

        InstallmentInteraction::create([
            'installment_id' => $installment?->id,
            'sale_id' => (int) $sale->id,
            'client_id' => (int) $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => InstallmentInteraction::TYPE_REPLY_BOLETO,
            'message' => $bodyText,
            'meta' => [
                'option' => '2',
                'from' => $from,
                'message_type' => $payload['type'] ?? null,
            ],
        ]);

        if ($installment === null) {
            $portalUrl = rtrim((string) config('app.url'), '/').'/pagamentos';
            $this->whatsapp->sendAndRecord(
                phone: $client->phone,
                message: "✅ *{$client->name}*, não encontramos parcelas em aberto neste contrato.\n\nPortal: {$portalUrl}",
                type: InstallmentInteraction::TYPE_REPLY_BOLETO.'_response',
                saleId: (int) $sale->id,
                clientId: (int) $client->id,
            );

            return;
        }

        $pixSent = $this->sendPixWhatsapp->execute(
            installment: $installment,
            phone: $this->replyAddress($from, $client),
            interactionType: InstallmentInteraction::TYPE_REPLY_BOLETO.'_pix',
        );

        $boletoResult = $this->sendBoletoWhatsapp->execute(
            installment: $installment,
            phone: $this->replyAddress($from, $client),
            interactionType: InstallmentInteraction::TYPE_REPLY_BOLETO.'_boleto',
        );

        if (! $pixSent && ! ($boletoResult['ok'] ?? false)) {
            $portalUrl = rtrim((string) config('app.url'), '/').'/pagamentos';
            $boletoError = (string) ($boletoResult['error'] ?? '');
            $message = str_contains(mb_strtolower($boletoError), 'limite') || str_contains(mb_strtolower($boletoError), 'máximo')
                ? "⚠️ *{$client->name}*, o boleto não pôde ser gerado:\n{$boletoError}\n\nTente o PIX acima ou pelo portal:\n🔗 {$portalUrl}"
                : "⚠️ *{$client->name}*, não foi possível gerar PIX nem boleto agora.\n\nTente pelo portal:\n🔗 {$portalUrl}\n\nOu digite *atendimento*.";

            $this->whatsapp->sendAndRecord(
                phone: $this->replyAddress($from, $client),
                message: $message,
                type: InstallmentInteraction::TYPE_REPLY_BOLETO.'_response',
                installmentId: $installment->id,
                saleId: (int) $sale->id,
                clientId: (int) $client->id,
            );
        }

        $this->whatsapp->notifySid(
            message: "💰 *{$client->name}* solicitou boleto/PIX atualizado.\n".
            'Contrato: '.str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y')."\n".
            "Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}\nFone: {$client->phone}",
            saleId: (int) $sale->id,
            clientId: (int) $client->id,
            relatedClientPhone: (string) $client->phone,
            type: InstallmentInteraction::TYPE_SID_NOTIFY,
            meta: ['trigger' => 'reply_payment', 'pix_sent' => $pixSent, 'boleto_sent' => $boletoResult['ok'] ?? false],
        );
    }

    private function resolvePaymentInstallment(Sale $sale, InstallmentInteraction $context): ?Installment
    {
        if ($context->installment_id !== null) {
            $installment = Installment::query()->find((int) $context->installment_id);

            if ($installment && $installment->status !== Installment::STATUS_PAID) {
                return $installment;
            }
        }

        $metaIds = is_array($context->meta['installment_ids'] ?? null)
            ? $context->meta['installment_ids']
            : [];

        if ($metaIds !== []) {
            $installment = Installment::query()
                ->whereIn('id', $metaIds)
                ->where('status', '!=', Installment::STATUS_PAID)
                ->orderBy('due_date')
                ->first();

            if ($installment) {
                return $installment;
            }
        }

        return Installment::query()
            ->where('sale_id', $sale->id)
            ->overdue()
            ->where('status', '!=', Installment::STATUS_PAID)
            ->orderBy('due_date')
            ->first()
            ?? Installment::query()
                ->where('sale_id', $sale->id)
                ->where('status', Installment::STATUS_PENDING)
                ->orderBy('due_date')
                ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processReminderButtonReply(
        array $payload,
        string $from,
        string $buttonId,
        InstallmentInteraction $lastOutbound,
    ): void {
        if ($buttonId === WhatsappReminderButtons::BTN_PAYMENT) {
            $this->processReply($payload, $from, '2', $lastOutbound);

            return;
        }

        $sale = $lastOutbound->sale;
        $client = $sale?->client;

        if (! $sale || ! $client) {
            return;
        }

        if (! $client->acceptsWhatsappNotifications()) {
            return;
        }

        $bodyText = $this->truncateMessage(trim((string) ($payload['body'] ?? $payload['content'] ?? $buttonId)));
        $phone = preg_replace('/[^0-9]/', '', $from) ?? $from;
        $sidWaMe = 'wa.me/'.$this->whatsapp->sidPhoneDigits();
        $sidDisplay = $this->formatSidPhoneDisplay();
        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y');
        $installment = $lastOutbound->installment;
        $parcelaLabel = $installment?->type === Installment::TYPE_DOWN_PAYMENT
            ? 'Entrada'
            : 'Parcela '.str_pad((string) ($installment?->number ?? 0), 2, '0', STR_PAD_LEFT);

        [$type, $replyMessage, $sidNotification] = match ($buttonId) {
            WhatsappReminderButtons::BTN_PAID => [
                InstallmentInteraction::TYPE_REPLY_ACKNOWLEDGE,
                "✅ Obrigado, *{$client->name}*! Vamos conferir o pagamento e atualizar seu cadastro em breve.\n\nQualquer dúvida: {$sidDisplay}\n_Sid360 Imóveis_",
                "📬 *{$client->name}* informou que já pagou (lembrete).\n".
                "Contrato: {$contractNo} · {$parcelaLabel}\n".
                "Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}\nFone: {$client->phone}",
            ],
            WhatsappReminderButtons::BTN_SUPPORT => [
                InstallmentInteraction::TYPE_REPLY_NEGOTIATE,
                "📞 Olá, *{$client->name}*! O corretor Sid foi notificado e entrará em contato em breve.\n\nOu se preferir, chame diretamente:\n📱 *{$sidWaMe}*\n_Sid360 Imóveis_",
                "🤝 *{$client->name}* pediu atendimento após lembrete de vencimento.\n".
                "Contrato: {$contractNo} · {$parcelaLabel}\n".
                "Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}\nFone: {$client->phone}\n\n⚡ Responda logo!",
            ],
            default => [
                InstallmentInteraction::TYPE_REPLY_UNKNOWN,
                "Olá! Não entendi sua resposta. Toque em um dos botões do lembrete ou fale com a gente: {$sidDisplay}",
                null,
            ],
        };

        $installmentId = $lastOutbound->installment_id !== null
            ? (int) $lastOutbound->installment_id
            : null;

        InstallmentInteraction::create([
            'installment_id' => $installmentId,
            'sale_id' => (int) $sale->id,
            'client_id' => (int) $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => $type,
            'message' => $bodyText,
            'meta' => [
                'button_id' => $buttonId,
                'from' => $from,
                'message_type' => $payload['type'] ?? null,
                'source' => 'reminder',
            ],
        ]);

        $this->whatsapp->sendAndRecord(
            phone: $client->phone,
            message: $replyMessage,
            type: $type.'_response',
            installmentId: $installmentId,
            saleId: (int) $sale->id,
            clientId: (int) $client->id,
        );

        if ($sidNotification) {
            $this->whatsapp->notifySid(
                message: $sidNotification,
                saleId: (int) $sale->id,
                clientId: (int) $client->id,
                relatedClientPhone: (string) $client->phone,
                type: InstallmentInteraction::TYPE_SID_NOTIFY,
                meta: ['trigger' => $type, 'button_id' => $buttonId, 'source' => 'reminder'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findLastReminderOutbound(array $payload, string $from, Carbon $since): ?InstallmentInteraction
    {
        $query = InstallmentInteraction::query()
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->where('type', InstallmentInteraction::TYPE_REMINDER)
            ->where('created_at', '>=', $since)
            ->where('meta->format', 'buttons')
            ->with(['sale.client', 'sale.lot.development', 'installment']);

        if ($from !== '') {
            $byChat = (clone $query)->where('meta->from', $from)->latest()->first();

            if ($byChat) {
                return $byChat;
            }
        }

        $digits = preg_replace('/[^0-9]/', '', $from) ?? '';

        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            $normalized = $this->normalizePhone($digits);

            return (clone $query)
                ->where(function ($phoneQuery) use ($digits, $normalized): void {
                    $phoneQuery->where('phone', 'like', "%{$normalized}%")
                        ->orWhere('phone', 'like', "%{$digits}%");
                })
                ->latest()
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findLastOutbound(array $payload, string $from, Carbon $since): ?InstallmentInteraction
    {
        $query = InstallmentInteraction::query()
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->where('type', InstallmentInteraction::TYPE_OVERDUE)
            ->where('created_at', '>=', $since)
            ->with(['sale.client', 'sale.lot.development', 'installment']);

        $saleId = $this->extractSaleIdFromQuoted($payload);

        if ($saleId !== null) {
            $bySale = (clone $query)->where('sale_id', $saleId)->latest()->first();

            if ($bySale) {
                return $bySale;
            }
        }

        if ($from !== '') {
            $byChat = (clone $query)->where('meta->from', $from)->latest()->first();

            if ($byChat) {
                return $byChat;
            }
        }

        $digits = preg_replace('/[^0-9]/', '', $from) ?? '';

        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            $normalized = $this->normalizePhone($digits);

            return (clone $query)
                ->where(function ($phoneQuery) use ($digits, $normalized): void {
                    $phoneQuery->where('phone', 'like', "%{$normalized}%")
                        ->orWhere('phone', 'like', "%{$digits}%");
                })
                ->latest()
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findLastBotMenuOutbound(array $payload, string $from, Carbon $since): ?InstallmentInteraction
    {
        $query = InstallmentInteraction::query()
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->where('type', InstallmentInteraction::TYPE_BOT_RESPONSE)
            ->where('created_at', '>=', $since)
            ->where('meta->format', 'list')
            ->where('meta->command', 'menu');

        if ($from !== '') {
            $byChat = (clone $query)->where('meta->from', $from)->latest()->first();

            if ($byChat) {
                return $byChat;
            }
        }

        $digits = preg_replace('/[^0-9]/', '', $from) ?? '';

        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            $normalized = $this->normalizePhone($digits);

            return (clone $query)
                ->where(function ($phoneQuery) use ($digits, $normalized): void {
                    $phoneQuery->where('phone', 'like', "%{$normalized}%")
                        ->orWhere('phone', 'like', "%{$digits}%");
                })
                ->latest()
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findLastBotContinuationOutbound(array $payload, string $from, Carbon $since): ?InstallmentInteraction
    {
        $query = InstallmentInteraction::query()
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->where('type', InstallmentInteraction::TYPE_BOT_RESPONSE)
            ->where('created_at', '>=', $since)
            ->where('meta->format', 'buttons')
            ->where('meta->interactive', 'continuation');

        if ($from !== '') {
            $byChat = (clone $query)->where('meta->from', $from)->latest()->first();

            if ($byChat) {
                return $byChat;
            }
        }

        $digits = preg_replace('/[^0-9]/', '', $from) ?? '';

        if (strlen($digits) >= 10 && strlen($digits) <= 13) {
            $normalized = $this->normalizePhone($digits);

            return (clone $query)
                ->where(function ($phoneQuery) use ($digits, $normalized): void {
                    $phoneQuery->where('phone', 'like', "%{$normalized}%")
                        ->orWhere('phone', 'like', "%{$digits}%");
                })
                ->latest()
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSaleIdFromQuoted(array $payload): ?int
    {
        $description = data_get($payload, 'quotedMsg.list.description')
            ?? data_get($payload, 'quotedMsg.list.list.description');

        if (! is_string($description)) {
            return null;
        }

        if (preg_match('/contrato \*0*(\d+)\/\d+\*/u', $description, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function formatSidPhoneDisplay(): string
    {
        $digits = $this->whatsapp->sidPhoneDigits();

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

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6, 4),
            );
        }

        return $digits;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '55') && strlen($digits) > 11) {
            return substr($digits, 2);
        }

        return $digits;
    }

    private function replyAddress(string $from, Client $client): string
    {
        $from = trim($from);

        if (str_contains($from, '@')) {
            return $from;
        }

        return (string) ($client->phone ?? $from);
    }
}
