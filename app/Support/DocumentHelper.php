<?php

declare(strict_types=1);

namespace App\Support;

final class DocumentHelper
{
    public static function digitsOnly(?string $value): string
    {
        return preg_replace('/\D/', '', $value ?? '') ?? '';
    }

    public static function phoneMatches(?string $storedPhone, ?string $inputPhone): bool
    {
        $stored = self::normalizePhone(self::digitsOnly($storedPhone));
        $input = self::normalizePhone(self::digitsOnly($inputPhone));

        if ($stored === '' || $input === '') {
            return false;
        }

        if ($stored === $input) {
            return true;
        }

        return strlen($input) >= 8 && str_ends_with($stored, substr($input, -8));
    }

    private static function normalizePhone(string $digits): string
    {
        $digits = ltrim($digits, '0');

        if (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
