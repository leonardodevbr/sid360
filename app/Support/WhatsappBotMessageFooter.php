<?php

declare(strict_types=1);

namespace App\Support;

final class WhatsappBotMessageFooter
{
    private const FOOTER_MARKER = 'Mensagem automática enviada pelo Sid360';

    private const FOOTER = "\n\n—\nMensagem automática enviada pelo Sid360.\nDigite ATENDIMENTO para falar com o corretor ou SAIR para pausar o assistente.";

    public static function append(string $message): string
    {
        if (str_contains($message, self::FOOTER_MARKER)) {
            return $message;
        }

        return rtrim($message).self::FOOTER;
    }
}
