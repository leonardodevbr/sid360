<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rodapé nativo das mensagens WhatsApp via WPPConnect.
 *
 * WhatsApp descarta footers acima de ~60 caracteres — manter curto.
 *
 * Contextos:
 * - automatic: disparo proativo (lembrete, inadimplência) — sem ação do cliente
 * - botSession: cliente iniciou conversa com o bot — pode encerrar com SAIR
 * - none: respostas do bot ao pedido do cliente (saldo, PIX, extrato, etc.)
 *
 * @see POST /api/{session}/send-message — body: { message, options: { footer } }
 * @see POST /api/{session}/send-list-message — body: { footer } no root do JSON
 */
final class WhatsappBotMessageFooter
{
    public static function automatic(): string
    {
        return 'Mensagem automática Sid360.';
    }

    public static function botSession(): string
    {
        return 'Digite SAIR para encerrar o assistente.';
    }

    /**
     * @return array{footer: string}
     */
    public static function automaticOptions(): array
    {
        return [
            'footer' => self::automatic(),
        ];
    }

    /**
     * @return array{footer: string}
     */
    public static function botSessionOptions(): array
    {
        return [
            'footer' => self::botSession(),
        ];
    }
}
