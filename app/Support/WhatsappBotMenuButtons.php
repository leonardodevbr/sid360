<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Menu interativo do bot (lista WPPConnect) — rowId → comando textual.
 */
final class WhatsappBotMenuButtons
{
    public const ROW_PAYMENT = 'bot_payment';

    public const ROW_BALANCE = 'bot_balance';

    public const ROW_STATEMENT = 'bot_statement';

    public const ROW_CONTRACT = 'bot_contract';

    public const ROW_CARNE = 'bot_carne';

    public const ROW_SUPPORT = 'bot_support';

    public const BUTTON_TEXT = 'Ver comandos';

    public const SECTION_TITLE = 'Assistente Sid360';

    /**
     * @return array<int, array{title: string, rows: list<array{rowId: string, title: string, description: string}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => self::SECTION_TITLE,
                'rows' => [
                    [
                        'rowId' => self::ROW_PAYMENT,
                        'title' => '2ª via / PIX ou boleto',
                        'description' => 'Receber link de pagamento atualizado',
                    ],
                    [
                        'rowId' => self::ROW_BALANCE,
                        'title' => 'Saldo pendente',
                        'description' => 'Ver parcelas em aberto',
                    ],
                    [
                        'rowId' => self::ROW_STATEMENT,
                        'title' => 'Extrato de pagamentos',
                        'description' => 'Histórico do que já foi pago',
                    ],
                    [
                        'rowId' => self::ROW_CONTRACT,
                        'title' => 'Contrato (PDF)',
                        'description' => 'Receber o contrato da compra',
                    ],
                    [
                        'rowId' => self::ROW_CARNE,
                        'title' => 'Carnê / promissória',
                        'description' => 'Receber carnê em PDF',
                    ],
                    [
                        'rowId' => self::ROW_SUPPORT,
                        'title' => 'Falar com o corretor',
                        'description' => 'Atendimento humano',
                    ],
                ],
            ],
        ];
    }

    public static function isBotMenuRowId(string $rowId): bool
    {
        return self::commandBodyFromRowId($rowId) !== null;
    }

    public static function commandBodyFromRowId(string $rowId): ?string
    {
        return match ($rowId) {
            self::ROW_PAYMENT => '2ª via',
            self::ROW_BALANCE => 'saldo',
            self::ROW_STATEMENT => 'extrato',
            self::ROW_CONTRACT => 'contrato',
            self::ROW_CARNE => 'carne',
            self::ROW_SUPPORT => 'atendimento',
            default => null,
        };
    }
}
