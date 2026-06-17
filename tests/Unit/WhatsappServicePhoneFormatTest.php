<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WhatsappService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappServicePhoneFormatTest extends TestCase
{
    private WhatsappService $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->whatsapp = new WhatsappService;
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function phoneProvider(): array
    {
        return [
            'jid from webhook' => ['5574988230151@c.us', ['5574988230151@c.us']],
            'local 11 digits' => ['74988230151', ['5574988230151@c.us']],
            'with country code' => ['5574988230151', ['5574988230151@c.us']],
            'formatted display' => ['(74) 98823-0151', ['5574988230151@c.us']],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function test_phone_as_wppconnect_contacts(string $input, array $expected): void
    {
        $this->assertSame($expected, $this->whatsapp->phoneAsWppconnectContacts($input));
    }

    public function test_phone_as_wppconnect_contacts_rejects_invalid(): void
    {
        $this->assertSame([], $this->whatsapp->phoneAsWppconnectContacts('123'));
    }
}
