<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\InstallmentPenaltyService;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsappService $whatsapp,
        private readonly InstallmentPenaltyService $penalty,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        Log::info('WhatsApp webhook received', ['payload' => $request->all()]);

        $event = $request->input('event');

        if ($event !== 'onmessage' && $event !== 'message') {
            return response()->json(['ok' => true]);
        }

        $payload = $this->resolvePayload($request);

        if ($request->boolean('fromMe') || ($payload['fromMe'] ?? false)) {
            return response()->json(['ok' => true]);
        }

        $from = $payload['from'] ?? $payload['chatId'] ?? $payload['sender']['id'] ?? null;
        $option = $this->extractOption($payload);

        if (! is_string($from) || $from === '' || $option === '') {
            Log::info('WhatsApp webhook: ignored message', [
                'from' => $from,
                'type' => $payload['type'] ?? null,
                'option' => $option,
            ]);

            return response()->json(['ok' => true]);
        }

        if (str_contains($from, '@g.us')) {
            return response()->json(['ok' => true]);
        }

        $this->processReply($payload, $from, $option);

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

        return array_merge($root, $nested);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOption(array $payload): string
    {
        $rowId = data_get($payload, 'listResponse.singleSelectReply.selectedRowId')
            ?? data_get($payload, 'selectedRowId');

        if (is_string($rowId) && $rowId !== '') {
            return trim($rowId);
        }

        $body = trim((string) ($payload['body'] ?? $payload['content'] ?? ''));

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
    private function processReply(array $payload, string $from, string $option): void
    {
        $windowHours = (int) Setting::get('whatsapp_reply_window_hours', 48);
        $since = Carbon::now()->subHours($windowHours);

        $lastOutbound = $this->findLastOutbound($payload, $from, $since);

        if (! $lastOutbound) {
            Log::info('WhatsApp webhook: no recent outbound for phone', [
                'from' => $from,
                'sale_id' => $this->extractSaleIdFromQuoted($payload),
            ]);

            return;
        }

        $sale = $lastOutbound->sale;
        $client = $sale?->client;

        if (! $sale || ! $client) {
            return;
        }

        $bodyText = trim((string) ($payload['body'] ?? $payload['content'] ?? $option));
        $phone = preg_replace('/[^0-9]/', '', $from) ?? $from;
        $fmt = fn (int $v): string => 'R$ '.number_format($v / 100, 2, ',', '.');
        $sidWaMe = $this->sidWaMeLink();
        $sidDisplay = $this->sidPhoneDisplay();

        [$type, $replyMessage, $sidNotification] = match ($option) {
            '1' => [
                InstallmentInteraction::TYPE_REPLY_ACKNOWLEDGE,
                "✅ Olá, *{$client->name}*! Recebemos sua confirmação.\n\nFique tranquilo(a), assim que o pagamento for realizado atualizaremos seu cadastro.\n\nQualquer dúvida: {$sidDisplay}\n_Sid360 Imóveis_",
                "📬 *{$client->name}* confirmou que vai regularizar o contrato ".
                str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y').".\n".
                "Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}\nFone: {$client->phone}",
            ],
            '2' => [
                InstallmentInteraction::TYPE_REPLY_BOLETO,
                $this->buildBoletoMessage($sale, $client, $lastOutbound, $fmt),
                "💰 *{$client->name}* solicitou boleto/PIX atualizado.\n".
                'Contrato: '.str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y')."\n".
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
            $this->whatsapp->send($this->sidPhoneDigits(), $sidNotification);
        }
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

    private function buildBoletoMessage(
        Sale $sale,
        Client $client,
        InstallmentInteraction $context,
        callable $fmt
    ): string {
        $overdue = Installment::query()
            ->where('sale_id', $sale->id)
            ->overdue()
            ->where('type', '!=', Installment::TYPE_DOWN_PAYMENT)
            ->orderBy('due_date')
            ->get();

        $contractNo = str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)
            .'/'.$sale->sale_date?->format('Y');

        $portalUrl = rtrim((string) config('app.url'), '/').'/pagamentos';

        if ($overdue->isEmpty()) {
            return "✅ *{$client->name}*, não encontramos parcelas em atraso no seu contrato {$contractNo}.\n\nAcesse seu extrato completo em:\n🔗 {$portalUrl}";
        }

        $summary = $this->penalty->summarize($overdue);
        $total = $fmt($summary['total_corrected_cents']);
        $payDate = $summary['payment_date']->format('d/m/Y');

        $parcelLines = $overdue->map(function (Installment $inst) use ($fmt, $summary): string {
            $days = $this->penalty->daysOverdue($inst->due_date, $summary['payment_date']);
            $corrected = $this->penalty->correctedAmountCents((int) $inst->value, $days);
            $number = str_pad((string) $inst->number, 2, '0', STR_PAD_LEFT);

            return "• Parcela *{$number}* — *{$fmt($corrected)}*";
        })->implode("\n");

        return implode("\n", [
            "✅ *{$client->name}*, recebemos seu pedido de pagamento!",
            '',
            "Contrato *{$contractNo}* · Q{$sale->lot?->block} · L{$sale->lot?->number}",
            '',
            '*Como pagar agora:*',
            "1️⃣ Acesse: {$portalUrl}",
            '2️⃣ Entre com seu CPF e escolha PIX ou boleto',
            '',
            "*Débito em aberto ({$summary['count']} parcela(s)):*",
            $parcelLines,
            '',
            "*Total até {$payDate}:* *{$total}*",
            '_Valores com multa estimada de 2,5% a.m._',
            '',
            'Em breve os boletos individuais também estarão disponíveis no portal.',
            '',
            "Dúvidas: {$this->sidPhoneDisplay()}",
            '_Sid360 Imóveis_',
        ]);
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
}
