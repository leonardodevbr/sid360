<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nome exibido ao cliente em mensagens automáticas (primeiro + último).
 */
final class ClientDisplayName
{
    public static function short(string $fullName): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/u', trim($fullName)) ?: []));

        if ($parts === []) {
            return $fullName;
        }

        if (count($parts) <= 2) {
            return implode(' ', $parts);
        }

        return $parts[0].' '.$parts[count($parts) - 1];
    }
}
