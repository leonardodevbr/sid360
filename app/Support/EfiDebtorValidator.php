<?php

declare(strict_types=1);

namespace App\Support;

use Efi\Exception\EfiException;
use InvalidArgumentException;

final class EfiDebtorValidator
{
    public static function digitsOnlyCpf(?string $cpf): string
    {
        return DocumentHelper::digitsOnly($cpf);
    }

    public static function assertValidCpf(string $cpfDigits, string $clientName): void
    {
        if (strlen($cpfDigits) !== 11) {
            throw new InvalidArgumentException(
                'CPF do cliente inválido no cadastro ('
                .self::formatCpf($cpfDigits !== '' ? $cpfDigits : 'vazio')
                ."). Cliente «{$clientName}». Corrija em Clientes.",
            );
        }
    }

    public static function assertNotConfiguredHolderCpf(string $cpfDigits, string $clientName): void
    {
        $holderCpfDigits = self::digitsOnlyCpf((string) config('services.efi.holder_cpf', ''));

        if ($holderCpfDigits !== '' && $holderCpfDigits === $cpfDigits) {
            throw new InvalidArgumentException(
                'CPF do cliente ('.self::formatCpf($cpfDigits).") coincide com EFI_HOLDER_CPF do .env.\n"
                ."Cliente «{$clientName}». Esse valor no .env deve ser o CPF do titular da conta Efi, não do cliente.",
            );
        }
    }

    public static function isSamePersonError(EfiException $e): bool
    {
        return str_contains(mb_strtolower($e->getMessage()), 'mesma pessoa');
    }

    public static function samePersonErrorMessage(string $cpfDigits, string $clientName, int $efiCode = 0): string
    {
        $code = $efiCode > 0 ? " (código Efi {$efiCode})" : '';

        return 'A Efi rejeitou'.$code.': recebedor e cliente não podem ser a mesma pessoa. '
            .'CPF enviado: '.self::formatCpf($cpfDigits)." (cliente «{$clientName}»).\n"
            .'Telefone não influencia essa validação — só o CPF. '
            .'Confira no painel Efí qual documento está no titular da conta ligada ao Client_Id da API.';
    }

    public static function formatCpf(string $digits): string
    {
        if (strlen($digits) !== 11) {
            return $digits;
        }

        return substr($digits, 0, 3).'.'
            .substr($digits, 3, 3).'.'
            .substr($digits, 6, 3).'-'
            .substr($digits, 9, 2);
    }
}
