<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WhatsappCommandParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappCommandParserTest extends TestCase
{
    private WhatsappCommandParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new WhatsappCommandParser;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function commandProvider(): array
    {
        return [
            'menu' => ['ajuda', WhatsappCommandParser::COMMAND_MENU, null],
            'greeting' => ['olá', WhatsappCommandParser::COMMAND_MENU, null],
            'payment pix short' => ['quero pix', WhatsappCommandParser::COMMAND_PAYMENT, null],
            'payment link' => ['link de pagamento', WhatsappCommandParser::COMMAND_PAYMENT, null],
            'payment natural' => ['manda o boleto por favor', WhatsappCommandParser::COMMAND_PAYMENT, null],
            'payment how to pay' => ['como posso pagar', WhatsappCommandParser::COMMAND_PAYMENT, null],
            'payment copy paste' => ['codigo pix', WhatsappCommandParser::COMMAND_PAYMENT, null],
            'second copy' => ['2ª via', WhatsappCommandParser::COMMAND_PAYMENT, null],
            'second copy alt' => ['segunda via', WhatsappCommandParser::COMMAND_PAYMENT, null],
            'balance' => ['saldo', WhatsappCommandParser::COMMAND_BALANCE, null],
            'statement' => ['extrato', WhatsappCommandParser::COMMAND_STATEMENT, null],
            'contract with number' => ['contrato 0001/2025', WhatsappCommandParser::COMMAND_CONTRACT, '0001/2025'],
            'contract natural' => ['me manda o contrato', WhatsappCommandParser::COMMAND_CONTRACT, null],
            'carne' => ['carne', WhatsappCommandParser::COMMAND_CARNE, null],
            'carne natural' => ['quero a promissória', WhatsappCommandParser::COMMAND_CARNE, null],
            'support' => ['falar com o sid', WhatsappCommandParser::COMMAND_SUPPORT, null],
            'pause sair' => ['SAIR', WhatsappCommandParser::COMMAND_PAUSE, null],
            'pause parar' => ['parar', WhatsappCommandParser::COMMAND_PAUSE, null],
            'resume menu' => ['MENU', WhatsappCommandParser::COMMAND_RESUME, null],
            'resume iniciar' => ['iniciar', WhatsappCommandParser::COMMAND_RESUME, null],
            'human corretor' => ['corretor', WhatsappCommandParser::COMMAND_HUMAN, null],
            'human falar com corretor' => ['falar com corretor', WhatsappCommandParser::COMMAND_HUMAN, null],
            'unknown' => ['xyzabc', WhatsappCommandParser::COMMAND_UNKNOWN, null],
        ];
    }

    #[DataProvider('commandProvider')]
    public function test_parse_command(string $body, string $expectedCommand, ?string $expectedArgument): void
    {
        [$command, $argument] = $this->parser->parse($body);

        $this->assertSame($expectedCommand, $command);
        $this->assertSame($expectedArgument, $argument);
    }
}
