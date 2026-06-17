<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WhatsappBotMenuButtons;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappBotMenuButtonsTest extends TestCase
{
    public function test_sections_include_all_bot_commands(): void
    {
        $sections = WhatsappBotMenuButtons::sections();

        $this->assertCount(1, $sections);
        $this->assertSame(WhatsappBotMenuButtons::SECTION_TITLE, $sections[0]['title']);
        $this->assertCount(6, $sections[0]['rows']);

        $rowIds = array_column($sections[0]['rows'], 'rowId');

        $this->assertSame([
            WhatsappBotMenuButtons::ROW_PAYMENT,
            WhatsappBotMenuButtons::ROW_BALANCE,
            WhatsappBotMenuButtons::ROW_STATEMENT,
            WhatsappBotMenuButtons::ROW_CONTRACT,
            WhatsappBotMenuButtons::ROW_CARNE,
            WhatsappBotMenuButtons::ROW_SUPPORT,
        ], $rowIds);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rowIdProvider(): array
    {
        return [
            'payment' => [WhatsappBotMenuButtons::ROW_PAYMENT, '2ª via'],
            'balance' => [WhatsappBotMenuButtons::ROW_BALANCE, 'saldo'],
            'statement' => [WhatsappBotMenuButtons::ROW_STATEMENT, 'extrato'],
            'contract' => [WhatsappBotMenuButtons::ROW_CONTRACT, 'contrato'],
            'carne' => [WhatsappBotMenuButtons::ROW_CARNE, 'carne'],
            'support' => [WhatsappBotMenuButtons::ROW_SUPPORT, 'atendimento'],
        ];
    }

    #[DataProvider('rowIdProvider')]
    public function test_command_body_from_row_id(string $rowId, string $expectedCommand): void
    {
        $this->assertTrue(WhatsappBotMenuButtons::isBotMenuRowId($rowId));
        $this->assertSame($expectedCommand, WhatsappBotMenuButtons::commandBodyFromRowId($rowId));
    }

    public function test_unknown_row_id_is_not_bot_menu(): void
    {
        $this->assertFalse(WhatsappBotMenuButtons::isBotMenuRowId('1'));
        $this->assertNull(WhatsappBotMenuButtons::commandBodyFromRowId('1'));
    }
}
