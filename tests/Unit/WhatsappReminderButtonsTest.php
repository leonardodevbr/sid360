<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WhatsappReminderButtons;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappReminderButtonsTest extends TestCase
{
    public function test_buttons_returns_three_quick_replies(): void
    {
        $buttons = WhatsappReminderButtons::buttons();

        $this->assertCount(3, $buttons);
        $this->assertSame(
            ['reminder_payment', 'reminder_paid', 'reminder_support'],
            array_column($buttons, 'id'),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bodyProvider(): array
    {
        return [
            'pix' => ['quero o pix', WhatsappReminderButtons::BTN_PAYMENT],
            'boleto' => ['preciso do boleto', WhatsappReminderButtons::BTN_PAYMENT],
            'paid' => ['já paguei ontem', WhatsappReminderButtons::BTN_PAID],
            'support' => ['falar com corretor', WhatsappReminderButtons::BTN_SUPPORT],
        ];
    }

    #[DataProvider('bodyProvider')]
    public function test_button_id_from_body(string $body, string $expectedId): void
    {
        $this->assertTrue(WhatsappReminderButtons::isReminderButtonId($expectedId));
        $this->assertSame($expectedId, WhatsappReminderButtons::buttonIdFromBody($body));
    }
}
