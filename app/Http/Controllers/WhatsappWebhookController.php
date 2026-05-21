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

        $data = $request->input('data', []);
        $from = $data['from'] ?? $data['sender']['id'] ?? null;
        $body = trim($data['body'] ?? $data['content'] ?? '');

        if (! $from || $body === '') {
            return response()->json(['ok' => true]);
        }

        if (str_contains($from, '@g.us')) {
            return response()->json(['ok' => true]);
        }

        $phone = preg_replace('/[^0-9]/', '', $from) ?? '';

        $this->processReply($phone, $body);

        return response()->json(['ok' => true]);
    }

    private function processReply(string $phone, string $body): void
    {
        $windowHours = (int) Setting::get('whatsapp_reply_window_hours', 48);
        $since = Carbon::now()->subHours($windowHours);
        $normalized = $this->normalizePhone($phone);

        $lastOutbound = InstallmentInteraction::query()
            ->where(function ($query) use ($phone, $normalized): void {
                $query->where('phone', 'like', "%{$normalized}%")
                    ->orWhere('phone', $phone);
            })
            ->where('direction', InstallmentInteraction::DIR_OUTBOUND)
            ->where('type', InstallmentInteraction::TYPE_OVERDUE)
            ->where('created_at', '>=', $since)
            ->with(['sale.client', 'sale.lot.development', 'installment'])
            ->latest()
            ->first();

        if (! $lastOutbound) {
            Log::info('WhatsApp webhook: no recent outbound for phone', ['phone' => $phone]);

            return;
        }

        $sale = $lastOutbound->sale;
        $client = $sale?->client;

        if (! $sale || ! $client) {
            return;
        }

        $option = trim($body);
        $fmt = fn (int $v): string => 'R$ '.number_format($v / 100, 2, ',', '.');

        [$type, $replyMessage, $sidNotification] = match ($option) {
            '1' => [
                InstallmentInteraction::TYPE_REPLY_ACKNOWLEDGE,
                "✅ Olá, *{$client->name}*! Recebemos sua confirmação.\n\nFique tranquilo(a), assim que o pagamento for realizado atualizaremos seu cadastro.\n\nQualquer dúvida: (74) 9 8823-0151\n_Sid360 Imóveis_",
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
                "📞 Olá, *{$client->name}*! O corretor Sid foi notificado e entrará em contato em breve.\n\nOu se preferir, chame diretamente:\n📱 *wa.me/5574988230151*\n_Sid360 Imóveis_",
                "🤝 *{$client->name}* quer negociar.\n".
                'Contrato: '.str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y')."\n".
                "Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}\nFone: {$client->phone}\n\n⚡ Responda logo!",
            ],
            default => [
                InstallmentInteraction::TYPE_REPLY_UNKNOWN,
                "Olá! Não entendi sua resposta. Por favor responda com:\n\n*1* - Estou ciente, vou regularizar\n*2* - Quero link de pagamento\n*3* - Preciso negociar\n\nOu fale com a gente: (74) 9 8823-0151",
                null,
            ],
        };

        InstallmentInteraction::create([
            'installment_id' => $lastOutbound->installment_id,
            'sale_id' => $sale->id,
            'client_id' => $client->id,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => $type,
            'message' => $body,
            'meta' => ['option' => $option],
        ]);

        $this->whatsapp->sendAndRecord(
            phone: $client->phone,
            message: $replyMessage,
            type: $type.'_response',
            installmentId: $lastOutbound->installment_id,
            saleId: $sale->id,
            clientId: $client->id,
        );

        if ($sidNotification) {
            $sidPhone = (string) Setting::get('whatsapp_sid_phone', '5574988230151');
            $this->whatsapp->send($sidPhone, $sidNotification);
        }
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
        $lines = $this->penalty->formatLinesForMessage($summary);
        $total = $fmt($summary['total_corrected_cents']);
        $payDate = $summary['payment_date']->format('d/m/Y');

        return implode("\n", [
            "💰 *{$client->name}*, aqui está o resumo atualizado:",
            '',
            "📋 Contrato: *{$contractNo}*",
            "🏠 Lote: Q{$sale->lot?->block} · L{$sale->lot?->number}",
            '',
            $lines,
            '',
            "💰 Total a pagar até *{$payDate}*: *{$total}*",
            '⚠️ Valor estimado com multa de 2,5% ao mês.',
            '',
            'Para pagar via PIX ou ver seu extrato completo:',
            "🔗 *{$portalUrl}*",
            '',
            'Dúvidas: (74) 9 8823-0151',
            '_Sid360 Imóveis_',
        ]);
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
