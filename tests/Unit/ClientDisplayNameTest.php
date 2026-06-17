<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ClientDisplayName;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientDisplayNameTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nameProvider(): array
    {
        return [
            'three parts' => ['Sidclei Souza Rocha', 'Sidclei Rocha'],
            'four parts' => ['Leonardo Nunes Oliveira', 'Leonardo Oliveira'],
            'two parts' => ['João Silva', 'João Silva'],
            'single' => ['Maria', 'Maria'],
            'extra spaces' => ['  Ana   Paula  Costa  ', 'Ana Costa'],
        ];
    }

    #[DataProvider('nameProvider')]
    public function test_short_name(string $full, string $expected): void
    {
        $this->assertSame($expected, ClientDisplayName::short($full));
    }
}
