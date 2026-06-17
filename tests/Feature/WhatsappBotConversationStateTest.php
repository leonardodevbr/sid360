<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InstallmentInteraction;
use App\Models\Setting;
use App\Models\WhatsappConversationState;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsappBotConversationStateTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_KEY = 'test-webhook-key-for-sid360';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.wppconnect.webhook_key' => self::WEBHOOK_KEY]);

        Setting::query()->create([
            'key' => 'whatsapp_bot_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'whatsapp',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_from_me_does_not_trigger_bot_response(): void
    {
        Client::query()->create([
            'name' => 'Cliente Teste',
            'cpf' => '52998224725',
            'phone' => '74988230151',
            'whatsapp_status' => Client::WHATSAPP_STATUS_CONFIRMED,
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('send');
        $whatsapp->shouldNotReceive('sendAndRecord');
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'body' => 'Oi',
            'fromMe' => true,
        ])->assertOk();

        $this->assertDatabaseMissing('installment_interactions', [
            'type' => InstallmentInteraction::TYPE_BOT_COMMAND,
        ]);
    }

    public function test_unknown_contact_does_not_receive_menu(): void
    {
        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('send');
        $whatsapp->shouldNotReceive('sendAndRecord');
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574999999999@c.us',
            'body' => 'Oi',
            'fromMe' => false,
        ])->assertOk();

        $this->assertDatabaseHas('installment_interactions', [
            'type' => InstallmentInteraction::TYPE_BOT_UNKNOWN_CONTACT,
            'message' => 'Oi',
        ]);
    }

    public function test_sair_pauses_bot_and_confirms_once(): void
    {
        $client = $this->createEligibleClient();

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendAndRecord')
            ->once()
            ->withArgs(fn (string $phone, string $message): bool => str_contains($message, 'pausada'));
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postWebhook($client, 'SAIR')->assertOk();

        $this->assertDatabaseHas('whatsapp_conversation_states', [
            'phone' => '5574988230151',
            'status' => WhatsappConversationState::STATUS_BOT_PAUSED,
            'client_id' => $client->id,
        ]);
    }

    public function test_paused_bot_does_not_respond_to_other_messages(): void
    {
        $client = $this->createEligibleClient();

        WhatsappConversationState::query()->create([
            'phone' => '5574988230151',
            'client_id' => $client->id,
            'status' => WhatsappConversationState::STATUS_BOT_PAUSED,
            'paused_at' => now(),
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendAndRecord');
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postWebhook($client, 'saldo')->assertOk();

        $this->assertDatabaseHas('installment_interactions', [
            'client_id' => $client->id,
            'type' => InstallmentInteraction::TYPE_BOT_IGNORED,
            'message' => 'saldo',
        ]);
    }

    public function test_iniciar_reactivates_bot_and_sends_menu(): void
    {
        $client = $this->createEligibleClient();

        WhatsappConversationState::query()->create([
            'phone' => '5574988230151',
            'client_id' => $client->id,
            'status' => WhatsappConversationState::STATUS_BOT_PAUSED,
            'paused_at' => now(),
        ]);

        Setting::query()->create([
            'key' => 'whatsapp_bot_menu_message',
            'value' => 'Menu Sid360 para {nome}',
            'type' => 'string',
            'group' => 'whatsapp',
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendAndRecord')->once()->andReturn(true);
        $whatsapp->shouldReceive('interpolate')->andReturnUsing(
            fn (string $template, array $vars): string => str_replace('{nome}', $vars['nome'] ?? '', $template),
        );
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postWebhook($client, 'INICIAR')->assertOk();

        $this->assertDatabaseHas('whatsapp_conversation_states', [
            'phone' => '5574988230151',
            'status' => WhatsappConversationState::STATUS_BOT_ACTIVE,
        ]);
    }

    public function test_atendimento_activates_human_mode_and_notifies(): void
    {
        $client = $this->createEligibleClient();

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendAndRecord')->once()->andReturn(true);
        $whatsapp->shouldReceive('notifySid')->once()->andReturn(true);
        $whatsapp->shouldReceive('sidPhoneDigits')->andReturn('5574988230151');
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postWebhook($client, 'atendimento')->assertOk();

        $state = WhatsappConversationState::query()->where('phone', '5574988230151')->first();
        $this->assertNotNull($state);
        $this->assertSame(WhatsappConversationState::STATUS_HUMAN, $state->status);
        $this->assertNotNull($state->human_until);
        $this->assertTrue($state->human_until->isFuture());
    }

    public function test_human_mode_ignores_messages_until_resume(): void
    {
        $client = $this->createEligibleClient();

        WhatsappConversationState::query()->create([
            'phone' => '5574988230151',
            'client_id' => $client->id,
            'status' => WhatsappConversationState::STATUS_HUMAN,
            'human_until' => now()->addHours(24),
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldNotReceive('sendAndRecord');
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postWebhook($client, 'saldo')->assertOk();

        $this->assertDatabaseHas('installment_interactions', [
            'client_id' => $client->id,
            'type' => InstallmentInteraction::TYPE_BOT_IGNORED,
        ]);
    }

    public function test_unknown_command_for_active_client_still_receives_menu(): void
    {
        $client = $this->createEligibleClient();

        Setting::query()->create([
            'key' => 'whatsapp_bot_menu_message',
            'value' => 'Menu {nome}',
            'type' => 'string',
            'group' => 'whatsapp',
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendAndRecord')
            ->once()
            ->withArgs(fn (string $phone, string $message): bool => str_contains($message, 'Não entendi'));
        $whatsapp->shouldReceive('interpolate')->andReturnUsing(
            fn (string $template): string => $template,
        );
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postWebhook($client, 'xyzabc')->assertOk();
    }

    public function test_existing_saldo_command_still_works_for_active_client(): void
    {
        $client = $this->createEligibleClient();

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendAndRecord')
            ->once()
            ->withArgs(fn (string $phone, string $message): bool => str_contains($message, 'contratos'));
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postWebhook($client, 'saldo')->assertOk();

        $this->assertDatabaseHas('installment_interactions', [
            'client_id' => $client->id,
            'type' => InstallmentInteraction::TYPE_BOT_COMMAND,
            'message' => 'saldo',
        ]);
    }

    private function createEligibleClient(): Client
    {
        return Client::query()->create([
            'name' => 'Leonardo Nunes Oliveira',
            'cpf' => '52998224725',
            'phone' => '74988230151',
            'whatsapp_status' => Client::WHATSAPP_STATUS_CONFIRMED,
        ]);
    }

    private function postWebhook(Client $client, string $body): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'body' => $body,
            'fromMe' => false,
            'type' => 'chat',
        ]);
    }
}
