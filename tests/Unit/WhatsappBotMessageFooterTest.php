<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WhatsappBotMessageFooter;
use Tests\TestCase;

class WhatsappBotMessageFooterTest extends TestCase
{
    public function test_wppconnect_options_contains_footer(): void
    {
        $options = WhatsappBotMessageFooter::wppconnectOptions();

        $this->assertArrayHasKey('footer', $options);
        $this->assertSame(WhatsappBotMessageFooter::text(), $options['footer']);
        $this->assertStringContainsString('Sid360', $options['footer']);
        $this->assertStringContainsString('ATENDIMENTO', $options['footer']);
        $this->assertStringContainsString('SAIR', $options['footer']);
        $this->assertLessThanOrEqual(60, mb_strlen($options['footer']));
    }

    public function test_footer_is_not_merged_into_message_body(): void
    {
        $body = 'Olá, sua parcela vence amanhã.';

        $this->assertStringNotContainsString(
            WhatsappBotMessageFooter::text(),
            $body,
        );
    }
}
