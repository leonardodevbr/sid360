<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use App\Support\WppconnectConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Apenas super-administradores podem acessar as configurações do sistema.');
        }
    }

    public function index(): JsonResponse
    {
        $this->ensureSuperAdmin();
        $this->authorize('settings.manage');

        $settings = Setting::query()
            ->whereNotIn('key', Setting::HIDDEN_KEYS)
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        $grouped = $this->mergeIntegrationSettings($settings)
            ->groupBy('group')
            ->map(function (Collection $groupSettings): Collection {
                return $groupSettings
                    ->map(fn (Setting $setting): array => $this->formatSettingForApi($setting))
                    ->values();
            });

        return response()->json($grouped);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $this->ensureSuperAdmin();
        $data = $request->validated();

        foreach ($data['settings'] as $item) {
            $key = $item['key'] ?? null;
            if (! is_string($key)) {
                continue;
            }
            if (Setting::isHiddenKey($key)) {
                continue;
            }
            if (in_array($key, Setting::MASKED_KEYS, true)) {
                $v = $item['value'] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
            }

            /** @var Setting $setting */
            $setting = Setting::query()->firstOrNew(['key' => $key]);

            if (isset($item['group'])) {
                $setting->group = $item['group'];
            } elseif (! $setting->exists) {
                $setting->group = 'general';
            }

            if (isset($item['type'])) {
                $setting->type = $item['type'];
            } elseif (! $setting->exists) {
                $setting->type = 'string';
            }

            $value = $item['value'] ?? null;
            if ($key === 'allowed_login_methods' && is_array($value) && count($value) === 0) {
                $value = ['email'];
            }
            $setting->setTypedValue($value);
            $setting->save();
        }

        Cache::forget('settings.all');

        return response()->json(['message' => 'Configurações atualizadas com sucesso.']);
    }

    /**
     * @param  Collection<int, Setting>  $settings
     * @return Collection<int, Setting>
     */
    private function mergeIntegrationSettings(Collection $settings): Collection
    {
        $existingKeys = $settings->pluck('key')->all();

        foreach (WppconnectConfig::INTEGRATION_KEYS as $key) {
            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $setting = new Setting([
                'key' => $key,
                'group' => 'whatsapp_integration',
                'type' => str_ends_with($key, '_timeout') ? 'integer' : 'string',
                'value' => null,
            ]);

            $settings->push($setting);
        }

        return $settings->sortBy([
            ['group', 'asc'],
            ['key', 'asc'],
        ])->values();
    }

    /**
     * @return array{
     *     key: string,
     *     value: mixed,
     *     type: string,
     *     masked: bool,
     *     configured: bool,
     *     source: 'database'|'env'|null
     * }
     */
    private function formatSettingForApi(Setting $setting): array
    {
        $value = $setting->getTypedValue();
        $hasDatabaseValue = WppconnectConfig::hasDatabaseValue($setting->key);
        $isIntegrationKey = in_array($setting->key, WppconnectConfig::INTEGRATION_KEYS, true);
        $isMasked = in_array($setting->key, Setting::MASKED_KEYS, true);

        if ($isIntegrationKey && ! $hasDatabaseValue) {
            $envValue = WppconnectConfig::envFallback($setting->key);
            $hasEnvValue = is_string($envValue)
                ? trim($envValue) !== ''
                : ($envValue !== null && $envValue !== 0);

            if ($isMasked) {
                return [
                    'key' => $setting->key,
                    'value' => null,
                    'type' => $setting->type,
                    'masked' => true,
                    'configured' => $hasEnvValue,
                    'source' => $hasEnvValue ? 'env' : null,
                ];
            }

            return [
                'key' => $setting->key,
                'value' => $hasEnvValue ? $envValue : ($setting->type === 'integer' ? null : ''),
                'type' => $setting->type,
                'masked' => false,
                'configured' => $hasEnvValue,
                'source' => $hasEnvValue ? 'env' : null,
            ];
        }

        if ($isMasked && $hasDatabaseValue) {
            return [
                'key' => $setting->key,
                'value' => null,
                'type' => $setting->type,
                'masked' => true,
                'configured' => true,
                'source' => 'database',
            ];
        }

        if ($isMasked) {
            return [
                'key' => $setting->key,
                'value' => null,
                'type' => $setting->type,
                'masked' => true,
                'configured' => false,
                'source' => null,
            ];
        }

        return [
            'key' => $setting->key,
            'value' => $value,
            'type' => $setting->type,
            'masked' => false,
            'configured' => $hasDatabaseValue || ($value !== null && $value !== ''),
            'source' => $hasDatabaseValue ? 'database' : null,
        ];
    }
}
