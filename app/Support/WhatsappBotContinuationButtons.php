<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Botões de continuação após concluir um serviço do bot (máx. 3).
 */
final class WhatsappBotContinuationButtons
{
    public const BTN_MORE_SERVICE = 'bot_more_service';

    public const BTN_END_SESSION = 'bot_end_session';

    /**
     * @return list<array{id: string, text: string}>
     */
    public static function buttons(): array
    {
        return [
            ['id' => self::BTN_MORE_SERVICE, 'text' => 'Outro serviço'],
            ['id' => self::BTN_END_SESSION, 'text' => 'Encerrar atendimento'],
        ];
    }

    public static function isContinuationButtonId(string $buttonId): bool
    {
        return self::commandBodyFromButtonId($buttonId) !== null;
    }

    public static function commandBodyFromButtonId(string $buttonId): ?string
    {
        return match ($buttonId) {
            self::BTN_MORE_SERVICE => 'menu',
            self::BTN_END_SESSION => 'sair',
            default => null,
        };
    }

    public static function buttonIdFromBody(string $body): ?string
    {
        $lower = mb_strtolower(trim($body));

        if ($lower === '') {
            return null;
        }

        if (
            str_contains($lower, 'outro serviço')
            || str_contains($lower, 'outro servico')
            || str_contains($lower, 'solicitar')
            || str_contains($lower, 'mais serviço')
            || str_contains($lower, 'mais servico')
        ) {
            return self::BTN_MORE_SERVICE;
        }

        if (
            str_contains($lower, 'encerrar')
            || str_contains($lower, 'finalizar')
            || str_contains($lower, 'não preciso')
            || str_contains($lower, 'nao preciso')
        ) {
            return self::BTN_END_SESSION;
        }

        return null;
    }
}
