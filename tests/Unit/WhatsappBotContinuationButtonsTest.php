<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WhatsappBotContinuationButtons;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappBotContinuationButtonsTest extends TestCase
{
    public function test_buttons_returns_two_quick_replies(): void
    {
        $buttons = WhatsappBotContinuationButtons::buttons();

        $this->assertCount(2, $buttons);
        $this->assertSame(
            ['bot_more_service', 'bot_end_session'],
            array_column($buttons, 'id'),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function buttonIdProvider(): array
    {
        return [
            'more service' => [WhatsappBotContinuationButtons::BTN_MORE_SERVICE, 'menu'],
            'end session' => [WhatsappBotContinuationButtons::BTN_END_SESSION, 'sair'],
        ];
    }

    #[DataProvider('buttonIdProvider')]
    public function test_command_body_from_button_id(string $buttonId, string $expected): void
    {
        $this->assertTrue(WhatsappBotContinuationButtons::isContinuationButtonId($buttonId));
        $this->assertSame($expected, WhatsappBotContinuationButtons::commandBodyFromButtonId($buttonId));
    }

    public function test_button_id_from_body(): void
    {
        $this->assertSame(
            WhatsappBotContinuationButtons::BTN_MORE_SERVICE,
            WhatsappBotContinuationButtons::buttonIdFromBody('quero outro serviço'),
        );
        $this->assertSame(
            WhatsappBotContinuationButtons::BTN_END_SESSION,
            WhatsappBotContinuationButtons::buttonIdFromBody('encerrar atendimento'),
        );
    }
}
