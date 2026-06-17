<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Development;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Lot;
use App\Models\Sale;
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

    public function test_webhook_routes_reminder_button_paid_reply(): void
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

        $development = Development::query()->create([
            'name' => 'Residencial Teste',
            'slug' => 'residencial-webhook',
            'status' => 'active',
        ]);

        $lot = Lot::query()->create([
            'development_id' => $development->id,
            'number' => '01',
            'block' => 'A',
            'area' => 300,
            'price' => 5000000,
            'status' => 'sold',
        ]);

        $sale = Sale::withoutEvents(function () use ($lot, $client): Sale {
            return Sale::query()->create([
                'lot_id' => $lot->id,
                'client_id' => $client->id,
                'sale_date' => now()->toDateString(),
                'total_value' => 5000000,
                'down_payment' => 0,
                'financed_value' => 5000000,
                'installments_count' => 1,
                'installment_value' => 5000000,
                'first_due_date' => now()->addDays(3)->toDateString(),
                'payment_day' => 10,
                'status' => 'active',
            ]);
        });

        $installment = Installment::query()->create([
            'sale_id' => $sale->id,
            'type' => Installment::TYPE_FINANCING,
            'number' => 1,
            'due_date' => now()->addDays(3)->toDateString(),
            'value' => 5000000,
            'status' => Installment::STATUS_PENDING,
        ]);

        InstallmentInteraction::query()->create([
            'installment_id' => $installment->id,
            'sale_id' => $sale->id,
            'client_id' => $client->id,
            'phone' => '74988230151',
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => InstallmentInteraction::TYPE_REMINDER,
            'message' => 'Lembrete de vencimento',
            'meta' => [
                'format' => 'buttons',
                'sent' => true,
            ],
        ]);

        $whatsapp = Mockery::mock(WhatsappService::class);
        $whatsapp->shouldReceive('sendAndRecord')->once()->andReturn(true);
        $whatsapp->shouldReceive('notifySid')->once()->andReturn(true);
        $whatsapp->shouldReceive('sidPhoneDigits')->andReturn('5574988230151');
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'body' => 'Já paguei',
            'fromMe' => false,
            'type' => 'buttons_response',
            'selectedButtonId' => 'reminder_paid',
        ])->assertOk();

        $this->assertDatabaseHas('installment_interactions', [
            'client_id' => $client->id,
            'type' => InstallmentInteraction::TYPE_REPLY_ACKNOWLEDGE,
            'direction' => InstallmentInteraction::DIR_INBOUND,
        ]);
    }
}
