<?php

declare(strict_types=1);

namespace App\Support;

final class WhatsappCommandParser
{
    public const COMMAND_MENU = 'menu';

    public const COMMAND_PAYMENT = 'payment';

    public const COMMAND_BALANCE = 'balance';

    public const COMMAND_STATEMENT = 'statement';

    public const COMMAND_CONTRACT = 'contract';

    public const COMMAND_CARNE = 'carne';

    public const COMMAND_SUPPORT = 'support';

    public const COMMAND_PAUSE = 'pause';

    public const COMMAND_RESUME = 'resume';

    public const COMMAND_HUMAN = 'human';

    public const COMMAND_UNKNOWN = 'unknown';

    /**
     * @return array{0: string, 1: string|null}
     */
    public function parse(string $body): array
    {
        $normalized = mb_strtolower(trim($body));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if ($normalized === '') {
            return [self::COMMAND_UNKNOWN, null];
        }

        if ($this->matchesPauseCommand($normalized)) {
            return [self::COMMAND_PAUSE, null];
        }

        if ($this->matchesResumeCommand($normalized)) {
            return [self::COMMAND_RESUME, null];
        }

        if ($this->matchesHumanCommand($normalized)) {
            return [self::COMMAND_HUMAN, null];
        }

        if ($this->matchesAny($normalized, [
            'menu', 'ajuda', 'help', 'comandos', 'opcoes', 'opções',
        ])) {
            return [self::COMMAND_MENU, null];
        }

        if ($this->matchesAny($normalized, [
            'oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'hey', 'hello', 'e ai', 'e aí',
        ])) {
            return [self::COMMAND_MENU, null];
        }

        if ($this->matchesPaymentCommand($normalized)) {
            return [self::COMMAND_PAYMENT, null];
        }

        if ($this->matchesCarneCommand($normalized)) {
            return [self::COMMAND_CARNE, $this->extractContractArgument($normalized)];
        }

        if ($this->matchesContractCommand($normalized)) {
            return [self::COMMAND_CONTRACT, $this->extractContractArgument($normalized)];
        }

        if ($this->matchesAny($normalized, [
            'saldo', 'pendente', 'pendentes', 'parcelas pendentes', 'parcelas em aberto',
            'devo', 'quanto devo', 'quanto falta', 'o que devo', 'dívida', 'divida',
        ])) {
            return [self::COMMAND_BALANCE, null];
        }

        if ($this->matchesAny($normalized, [
            'extrato', 'historico', 'histórico', 'pagamentos', 'pago', 'pagos', 'já paguei', 'ja paguei',
        ])) {
            return [self::COMMAND_STATEMENT, null];
        }

        if ($this->matchesSupportCommand($normalized)) {
            return [self::COMMAND_SUPPORT, null];
        }

        return [self::COMMAND_UNKNOWN, null];
    }

    private function matchesPauseCommand(string $normalized): bool
    {
        return in_array($normalized, [
            'sair',
            'parar',
            'cancelar',
            'encerrar',
            'remover',
        ], true);
    }

    private function matchesResumeCommand(string $normalized): bool
    {
        return in_array($normalized, [
            'iniciar',
            'menu',
            'voltar',
        ], true);
    }

    private function matchesHumanCommand(string $normalized): bool
    {
        if (in_array($normalized, ['humano', 'corretor'], true)) {
            return true;
        }

        return $this->matchesAny($normalized, [
            'falar com corretor',
            'falar com o corretor',
        ]);
    }

    private function matchesPaymentCommand(string $normalized): bool
    {
        $exact = [
            '2via', '2 via', '2ª via', '2a via', '2ªvia', '2a via', 'segunda via', 'segunda-via',
            'pagar', 'pagamento', 'quero pagar', 'como pagar', 'como pago', 'pagar parcela',
            'pagar agora', 'quitar', 'quitar parcela', 'link de pagamento', 'link pagamento',
            'dados para pagamento', 'forma de pagamento', 'codigo pix', 'código pix',
            'linha digitavel', 'linha digitável', 'chave pix', 'copia e cola', 'copia e cola pix',
        ];

        if ($this->matchesAny($normalized, $exact)) {
            return true;
        }

        $patterns = [
            '/\b2\s*ª?\s*via\b/u',
            '/\bsegunda\s+via\b/u',
            '/\b(quero|preciso|manda|mandar|enviar|me\s+manda|me\s+envia)\s+(o\s+)?(pix|boleto|pagamento|link)\b/u',
            '/\b(quero|preciso)\s+(do\s+)?(link|pix|boleto)\b/u',
            '/\b(manda|mandar|enviar)\s+(o\s+)?(pix|boleto)\b/u',
            '/\b(pix|boleto)\s+(atualizado|atualizada|novo|nova)\b/u',
            '/\b(pagar|pagamento)\s+(da\s+)?parcela\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return preg_match('/\b(pix|boleto)\b/u', $normalized) === 1;
    }

    private function matchesContractCommand(string $normalized): bool
    {
        if (preg_match('/^contrato(?:\s+(.+))?$/u', $normalized)) {
            return true;
        }

        $patterns = [
            '/\b(meu|o|um|enviar|manda|mandar|ver|baixar|copia|cópia|me\s+manda|me\s+envia)\s+(o\s+|do\s+|a\s+)?contrato\b/u',
            '/\bcontrato\s+(pdf|assinado|da\s+compra|do\s+lote)\b/u',
            '/\bpdf\s+do\s+contrato\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return $this->matchesAny($normalized, [
            'meu contrato', 'copia do contrato', 'cópia do contrato', 'documento da compra',
        ]);
    }

    private function matchesCarneCommand(string $normalized): bool
    {
        if (preg_match('/^(?:carne|carnê|promissoria|promissória)(?:\s+(.+))?$/u', $normalized)) {
            return true;
        }

        $patterns = [
            '/\b(quero|preciso|manda|mandar|enviar|me\s+manda)\s+(a\s+)?(carne|carnê|promissoria|promissória)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return $this->matchesAny($normalized, [
            'carne de pagamento', 'carnê de pagamento', 'promissoria do lote', 'promissória do lote',
            'folhas do carne', 'folhas do carnê',
        ]);
    }

    private function extractContractArgument(string $normalized): ?string
    {
        foreach ([
            '/^(?:contrato|carne|carnê|promissoria|promissória)\s+(.+)$/u',
            '/\bcontrato\s+(0*\d+\/\d{4})\b/u',
            '/\b(0*\d+\/\d{4})\b/u',
        ] as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                $argument = trim($matches[1]);

                return $argument !== '' ? $argument : null;
            }
        }

        return null;
    }

    private function matchesSupportCommand(string $normalized): bool
    {
        return $this->matchesAny($normalized, [
            'atendimento', 'falar com sid', 'falar com o sid', 'negociar',
            'suporte', 'falar com alguem', 'falar com alguém', 'preciso de ajuda',
        ]);
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($haystack === $needle || str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
