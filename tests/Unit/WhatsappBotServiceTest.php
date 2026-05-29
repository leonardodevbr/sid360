<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Installment\SendInstallmentBoletoWhatsappAction;
use App\Actions\Installment\SendInstallmentPixWhatsappAction;
use App\Actions\Sale\GenerateSaleContractPdfAction;
use App\Services\WhatsappBotService;
use App\Services\WhatsappService;
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
            sendPix: $this->createMock(SendInstallmentPixWhatsappAction::class),
            sendBoleto: $this->createMock(SendInstallmentBoletoWhatsappAction::class),
            generateContract: $this->createMock(GenerateSaleContractPdfAction::class),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function commandProvider(): array
    {
        return [
            'menu' => ['ajuda', WhatsappBotService::COMMAND_MENU, null],
            'greeting' => ['olá', WhatsappBotService::COMMAND_MENU, null],
            'payment pix' => ['quero pix', WhatsappBotService::COMMAND_PAYMENT, null],
            'second copy' => ['2ª via', WhatsappBotService::COMMAND_PAYMENT, null],
            'balance' => ['saldo', WhatsappBotService::COMMAND_BALANCE, null],
            'statement' => ['extrato', WhatsappBotService::COMMAND_STATEMENT, null],
            'contract with number' => ['contrato 0001/2025', WhatsappBotService::COMMAND_CONTRACT, '0001/2025'],
            'support' => ['falar com o sid', WhatsappBotService::COMMAND_SUPPORT, null],
            'unknown' => ['xyzabc', WhatsappBotService::COMMAND_UNKNOWN, null],
        ];
    }

    #[DataProvider('commandProvider')]
    public function test_parse_command(string $body, string $expectedCommand, ?string $expectedArgument): void
    {
        [$command, $argument] = $this->bot->parseCommand($body);

        $this->assertSame($expectedCommand, $command);
        $this->assertSame($expectedArgument, $argument);
    }
}
