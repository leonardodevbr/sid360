<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Botões de resposta rápida no lembrete de vencimento (máx. 3).
 */
final class WhatsappReminderButtons
{
    public const BTN_PAYMENT = 'reminder_payment';

    public const BTN_PAID = 'reminder_paid';

    public const BTN_SUPPORT = 'reminder_support';

    /**
     * @return list<array{id: string, text: string}>
     */
    public static function buttons(): array
    {
        return [
            ['id' => self::BTN_PAYMENT, 'text' => 'Quero PIX/boleto'],
            ['id' => self::BTN_PAID, 'text' => 'Já paguei'],
            ['id' => self::BTN_SUPPORT, 'text' => 'Falar com corretor'],
        ];
    }

    public static function isReminderButtonId(string $buttonId): bool
    {
        return in_array($buttonId, [
            self::BTN_PAYMENT,
            self::BTN_PAID,
            self::BTN_SUPPORT,
        ], true);
    }

    public static function buttonIdFromBody(string $body): ?string
    {
        $lower = mb_strtolower(trim($body));

        if ($lower === '') {
            return null;
        }

        if (str_contains($lower, 'pix') || str_contains($lower, 'boleto') || str_contains($lower, 'pagar')) {
            return self::BTN_PAYMENT;
        }

        if (str_contains($lower, 'já paguei') || str_contains($lower, 'ja paguei') || str_contains($lower, 'paguei')) {
            return self::BTN_PAID;
        }

        if (str_contains($lower, 'corretor') || str_contains($lower, 'atendimento') || str_contains($lower, 'falar')) {
            return self::BTN_SUPPORT;
        }

        return null;
    }
}
