<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

class WppconnectConfig
{
    /** @var list<string> */
    public const INTEGRATION_KEYS = [
        'wppconnect_base_url',
        'wppconnect_session',
        'wppconnect_token',
        'whatsapp_webhook_key',
        'wppconnect_timeout',
        'wppconnect_media_timeout',
    ];

    /** @var list<string> */
    public const MASKED_INTEGRATION_KEYS = [
        'wppconnect_token',
        'whatsapp_webhook_key',
    ];

    public static function baseUrl(): string
    {
        return rtrim(self::resolveString('wppconnect_base_url', 'services.wppconnect.base_url'), '/');
    }

    public static function session(): string
    {
        return self::resolveString('wppconnect_session', 'services.wppconnect.session');
    }

    public static function token(): string
    {
        return self::resolveString('wppconnect_token', 'services.wppconnect.token');
    }

    public static function messageToken(): string
    {
        $tokenFull = self::token();

        if ($tokenFull === '') {
            return '';
        }

        if (str_contains($tokenFull, ':')) {
            return substr($tokenFull, strpos($tokenFull, ':') + 1);
        }

        return $tokenFull;
    }

    public static function webhookKey(): string
    {
        return self::resolveString('whatsapp_webhook_key', 'services.wppconnect.webhook_key');
    }

    public static function timeout(): int
    {
        return self::resolveInteger('wppconnect_timeout', 'services.wppconnect.timeout', 30);
    }

    public static function mediaTimeout(): int
    {
        return self::resolveInteger('wppconnect_media_timeout', 'services.wppconnect.media_timeout', 90);
    }

    /**
     * @return array{base_url: string, session: string, token: string}|null
     */
    public static function resolved(): ?array
    {
        $baseUrl = self::baseUrl();
        $session = self::session();
        $token = self::messageToken();

        if ($baseUrl === '' || $session === '' || $token === '') {
            return null;
        }

        return [
            'base_url' => $baseUrl,
            'session' => $session,
            'token' => $token,
        ];
    }

    public static function isConfigured(): bool
    {
        return self::resolved() !== null;
    }

    public static function envFallback(string $key): mixed
    {
        return match ($key) {
            'wppconnect_base_url' => rtrim((string) config('services.wppconnect.base_url', ''), '/'),
            'wppconnect_session' => (string) config('services.wppconnect.session', ''),
            'wppconnect_token' => (string) config('services.wppconnect.token', ''),
            'whatsapp_webhook_key' => (string) config('services.wppconnect.webhook_key', ''),
            'wppconnect_timeout' => (int) config('services.wppconnect.timeout', 30),
            'wppconnect_media_timeout' => (int) config('services.wppconnect.media_timeout', 90),
            default => null,
        };
    }

    public static function hasDatabaseValue(string $key): bool
    {
        $setting = Setting::query()->where('key', $key)->first();

        if ($setting === null) {
            return false;
        }

        $value = $setting->getTypedValue();

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    public static function hasEffectiveValue(string $key): bool
    {
        if (self::hasDatabaseValue($key)) {
            return true;
        }

        $envValue = self::envFallback($key);

        if (is_string($envValue)) {
            return trim($envValue) !== '';
        }

        return $envValue !== null && $envValue !== 0;
    }

    private static function resolveString(string $settingKey, string $configKey): string
    {
        if (self::hasDatabaseValue($settingKey)) {
            return trim((string) Settings::get($settingKey, ''));
        }

        return (string) config($configKey, '');
    }

    private static function resolveInteger(string $settingKey, string $configKey, int $default): int
    {
        if (self::hasDatabaseValue($settingKey)) {
            return (int) Settings::get($settingKey, $default);
        }

        return (int) config($configKey, $default);
    }
}
