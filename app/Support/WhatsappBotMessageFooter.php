<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rodapé nativo das mensagens automáticas do bot via WPPConnect.
 *
 * @see https://wppconnect.io/wppconnect/functions/WAJS.chat.sendTextMessage.html
 * @see POST /api/{session}/send-message — body: { message, options: { footer } }
 *
 * Botões/listas via send-message (options.buttons) ou send-list-message.
 */
final class WhatsappBotMessageFooter
{
    public static function text(): string
    {
        return 'Mensagem automática enviada pelo Sid360. Digite ATENDIMENTO para falar com o corretor ou SAIR para pausar o assistente.';
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
