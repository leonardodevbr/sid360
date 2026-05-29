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

    private const WEBHOOK_KEY = '13d20efe60baa341cc8fcdfbb5ce0be69ca94894a8a37cde40d11325a8a7a97f';

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
        $whatsapp->shouldReceive('sendAndRecord')
            ->once()
            ->withArgs(function (string $phone, string $message): bool {
                return str_contains($phone, '74988230151')
                    && str_contains($message, 'Sid360')
                    && str_contains($message, '2ª via');
            })
            ->andReturn(true);
        $whatsapp->shouldReceive('interpolate')->andReturnUsing(
            fn (string $template, array $vars): string => str_replace(
                array_map(fn (string $key): string => '{'.$key.'}', array_keys($vars)),
                array_values($vars),
                $template,
            ),
        );
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
        $whatsapp->shouldReceive('sendAndRecord')->once()->andReturn(true);
        $whatsapp->shouldReceive('interpolate')->andReturnUsing(
            fn (string $template): string => $template,
        );
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
        $whatsapp->shouldReceive('sendAndRecord')->once()->andReturn(true);
        $whatsapp->shouldReceive('interpolate')->andReturnUsing(
            fn (string $template): string => $template,
        );
        $this->app->instance(WhatsappService::class, $whatsapp);

        $this->postJson('/api/whatsapp/webhook?key='.self::WEBHOOK_KEY, [
            'event' => 'onmessage',
            'from' => '5574988230151@c.us',
            'content' => 'menu',
            'fromMe' => false,
        ])->assertOk();
    }
}
