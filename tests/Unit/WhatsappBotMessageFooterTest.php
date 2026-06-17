<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WhatsappBotMessageFooter;
use Tests\TestCase;

class WhatsappBotMessageFooterTest extends TestCase
{
    public function test_automatic_footer_for_proactive_messages(): void
    {
        $options = WhatsappBotMessageFooter::automaticOptions();

        $this->assertArrayHasKey('footer', $options);
        $this->assertSame('Mensagem automática Sid360.', $options['footer']);
        $this->assertStringNotContainsString('SAIR', $options['footer']);
        $this->assertLessThanOrEqual(60, mb_strlen($options['footer']));
    }

    public function test_bot_session_footer_mentions_sair_only(): void
    {
        $options = WhatsappBotMessageFooter::botSessionOptions();

        $this->assertArrayHasKey('footer', $options);
        $this->assertSame('Digite SAIR para encerrar o assistente.', $options['footer']);
        $this->assertStringContainsString('SAIR', $options['footer']);
        $this->assertStringNotContainsString('ATENDIMENTO', $options['footer']);
        $this->assertLessThanOrEqual(60, mb_strlen($options['footer']));
    }

    public function test_footer_is_not_merged_into_message_body(): void
    {
        $body = 'Olá, sua parcela vence amanhã.';

        $this->assertStringNotContainsString(
            WhatsappBotMessageFooter::automatic(),
            $body,
        );
        $this->assertStringNotContainsString(
            WhatsappBotMessageFooter::botSession(),
            $body,
        );
    }
}
