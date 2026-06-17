<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rodapé nativo das mensagens automáticas do bot via WPPConnect.
 *
 * WhatsApp descarta footers acima de ~60 caracteres — manter curto.
 *
 * @see POST /api/{session}/send-message — body: { message, options: { footer } }
 * @see POST /api/{session}/send-list-message — body: { footer } no root do JSON
 */
final class WhatsappBotMessageFooter
{
    public static function text(): string
    {
        return 'Sid360 automático. Digite ATENDIMENTO ou SAIR.';
    }

    /**
     * Opções repassadas ao WPPConnect em send-message / sendText.
     *
     * @return array{footer: string}
     */
    public static function wppconnectOptions(): array
    {
        return [
            'footer' => self::text(),
        ];
    }
}
