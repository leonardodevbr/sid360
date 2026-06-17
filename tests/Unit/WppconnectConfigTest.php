<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\Settings;
use App\Support\WppconnectConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WppconnectConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wppconnect.base_url' => 'https://env-wpp.example',
            'services.wppconnect.session' => 'EnvSession',
            'services.wppconnect.token' => 'env-token',
            'services.wppconnect.webhook_key' => 'env-webhook-key',
            'services.wppconnect.timeout' => 25,
            'services.wppconnect.media_timeout' => 80,
        ]);
    }

    public function test_uses_env_when_database_is_empty(): void
    {
        $this->assertSame('https://env-wpp.example', WppconnectConfig::baseUrl());
        $this->assertSame('EnvSession', WppconnectConfig::session());
        $this->assertSame('env-token', WppconnectConfig::messageToken());
        $this->assertSame('env-webhook-key', WppconnectConfig::webhookKey());
        $this->assertSame(25, WppconnectConfig::timeout());
        $this->assertSame(80, WppconnectConfig::mediaTimeout());
    }

    public function test_prefers_database_over_env(): void
    {
        Settings::set('wppconnect_base_url', 'https://db-wpp.example', 'string', 'whatsapp_integration');
        Settings::set('wppconnect_session', 'DbSession', 'string', 'whatsapp_integration');
        Settings::set('wppconnect_token', 'session-name:db-token', 'string', 'whatsapp_integration');
        Settings::set('whatsapp_webhook_key', 'db-webhook-key', 'string', 'whatsapp_integration');
        Settings::set('wppconnect_timeout', 40, 'integer', 'whatsapp_integration');
        Settings::set('wppconnect_media_timeout', 120, 'integer', 'whatsapp_integration');

        Cache::forget('settings.all');

        $this->assertSame('https://db-wpp.example', WppconnectConfig::baseUrl());
        $this->assertSame('DbSession', WppconnectConfig::session());
        $this->assertSame('db-token', WppconnectConfig::messageToken());
        $this->assertSame('db-webhook-key', WppconnectConfig::webhookKey());
        $this->assertSame(40, WppconnectConfig::timeout());
        $this->assertSame(120, WppconnectConfig::mediaTimeout());
    }

    public function test_empty_database_value_falls_back_to_env(): void
    {
        Setting::query()->create([
            'key' => 'wppconnect_base_url',
            'value' => '',
            'type' => 'string',
            'group' => 'whatsapp_integration',
        ]);

        Cache::forget('settings.all');

        $this->assertSame('https://env-wpp.example', WppconnectConfig::baseUrl());
    }
}
