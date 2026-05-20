<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    private const CACHE_KEY = 'settings.all';

    /**
     * @param mixed $default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var array<string, mixed> $settings */
        $settings = Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::query()
                ->get()
                ->mapWithKeys(function (Setting $setting): array {
                    return [$setting->key => $setting->getTypedValue()];
                })
                ->all();
        });

        return $settings[$key] ?? $default;
    }

    /**
     * @param mixed $value
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $group = null): void
    {
        $setting = Setting::query()->firstOrNew(['key' => $key]);

        if ($type !== null) {
            $setting->type = $type;
        } elseif (! $setting->exists) {
            $setting->type = self::inferType($value);
        }

        if ($group !== null) {
            $setting->group = $group;
        } elseif (! $setting->exists) {
            $setting->group = 'general';
        }

        $setting->setTypedValue($value);
        $setting->save();

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param mixed $value
     */
    private static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}

