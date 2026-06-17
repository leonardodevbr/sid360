<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InstallmentInteraction;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsappWebhookBotTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_KEY = 'test-webhook-key-for-sid360';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.wppconnect.webhook_key' => self::WEBHOOK_KEY]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_webhook_replies_menu_to_greeting(): void
    {
        Setting::query()->create([
            'key' => 'whatsapp_bot_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);

        Client::query()->create([
            'name' => 'Leonardo Nunes Oliveira',
            'cpf' => '52998224725',
            'phone' => '74988230151',
            'whatsapp_status' => Client::WHATSAPP_STATUS_CONFIRMED,
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendListAndRecord')
            ->once()
            ->withArgs(function (string $phone, string $description): bool {
                return str_contains($phone, '74988230151')
                    && str_contains($description, 'Sid360')
                    && str_contains($description, 'Toque no botão');
            })
            ->andReturn(true);
        $this->app->instance(WhatsappService::class, $whatsapp);

        $response = $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'body' => 'Oi',
            'fromMe' => false,
            'type' => 'chat',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('installment_interactions', [
            'direction' => InstallmentInteraction::DIR_INBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_COMMAND,
            'message' => 'Oi',
        ]);
    }

    public function test_webhook_replies_menu_to_ajuda(): void
    {
        Setting::query()->create([
            'key' => 'whatsapp_bot_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);

        Client::query()->create([
            'name' => 'Cliente Teste',
            'cpf' => '39053344705',
            'phone' => '(74) 98823-0151',
            'whatsapp_status' => Client::WHATSAPP_STATUS_CONFIRMED,
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendListAndRecord')->once()->andReturn(true);
        $this->app->instance(WhatsappService::class, $whatsapp);

        $response = $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'content' => 'Ajuda',
            'fromMe' => false,
        ]);

        $response->assertOk();
    }

    public function test_webhook_uses_content_field_when_body_is_empty(): void
    {
        Setting::query()->create([
            'key' => 'whatsapp_bot_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);

        Client::query()->create([
            'name' => 'Cliente Teste',
            'cpf' => '39053344705',
            'phone' => '74988230151',
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendListAndRecord')->once()->andReturn(true);
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'content' => 'menu',
            'fromMe' => false,
        ])->assertOk();
    }

    public function test_webhook_routes_bot_menu_list_reply_to_command(): void
    {
        Setting::query()->create([
            'key' => 'whatsapp_bot_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);

        $client = Client::query()->create([
            'name' => 'Leonardo Nunes Oliveira',
            'cpf' => '52998224725',
            'phone' => '74988230151',
            'whatsapp_status' => Client::WHATSAPP_STATUS_CONFIRMED,
        ]);

        InstallmentInteraction::query()->create([
            'client_id' => $client->id,
            'phone' => '74988230151',
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => InstallmentInteraction::TYPE_BOT_RESPONSE,
            'message' => 'Menu interativo',
            'meta' => [
                'format' => 'list',
                'command' => 'menu',
                'sent' => true,
            ],
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendAndRecord')
            ->once()
            ->withArgs(fn (string $phone, string $message): bool => str_contains($message, 'contratos'));
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'body' => '',
            'fromMe' => false,
            'type' => 'list_response',
            'listResponse' => [
                'singleSelectReply' => [
                    'selectedRowId' => 'bot_balance',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('installment_interactions', [
            'client_id' => $client->id,
            'type' => InstallmentInteraction::TYPE_BOT_COMMAND,
            'message' => 'saldo',
        ]);
    }
}
