<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Installment\SendInstallmentBoletoWhatsappAction;
use App\Actions\Installment\SendInstallmentPixWhatsappAction;
use App\Actions\Sale\SendSaleCarneWhatsappAction;
use App\Actions\Sale\SendSaleContractWhatsappAction;
use App\Services\WhatsappBotService;
use App\Services\WhatsappConversationStateService;
use App\Services\WhatsappService;
use App\Support\WhatsappCommandParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappBotServiceTest extends TestCase
{
    private WhatsappBotService $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = new WhatsappBotService(
            whatsapp: $this->createMock(WhatsappService::class),
            commandParser: new WhatsappCommandParser,
            conversationState: $this->createMock(WhatsappConversationStateService::class),
            sendPix: $this->createMock(SendInstallmentPixWhatsappAction::class),
            sendBoleto: $this->createMock(SendInstallmentBoletoWhatsappAction::class),
            sendContract: $this->createMock(SendSaleContractWhatsappAction::class),
            sendCarne: $this->createMock(SendSaleCarneWhatsappAction::class),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function commandProvider(): array
    {
        return [
            'payment natural' => ['quero pagar', WhatsappBotService::COMMAND_PAYMENT, null],
            'contract with number' => ['contrato 0001/2025', WhatsappBotService::COMMAND_CONTRACT, '0001/2025'],
        ];
    }

    #[DataProvider('commandProvider')]
    public function test_parse_command_delegates_to_parser(string $body, string $expectedCommand, ?string $expectedArgument): void
    {
        [$command, $argument] = $this->bot->parseCommand($body);

        $this->assertSame($expectedCommand, $command);
        $this->assertSame($expectedArgument, $argument);
    }
}
