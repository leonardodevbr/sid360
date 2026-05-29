<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Client\FindClientByWhatsappPhoneAction;
use App\Models\Client;
use App\Models\InstallmentInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindClientByWhatsappPhoneActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_client_by_whatsapp_chat_id_from_previous_interaction(): void
    {
        $client = Client::query()->create([
            'name' => 'Cliente Teste',
            'cpf' => '52998224725',
            'phone' => '619992495212',
        ]);

        InstallmentInteraction::query()->create([
            'client_id' => $client->id,
            'phone' => '619992495212',
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => InstallmentInteraction::TYPE_REPLY_BOLETO,
            'message' => '2',
            'meta' => ['from' => '5511999998888@lid'],
        ]);

        $found = (new FindClientByWhatsappPhoneAction())->execute('5511999998888@lid');

        $this->assertNotNull($found);
        $this->assertSame($client->id, $found->id);
    }
}
