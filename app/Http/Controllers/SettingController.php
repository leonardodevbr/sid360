<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Apenas super-administradores podem acessar as configurações do sistema.');
        }
    }

    public function index(): JsonResponse
    {
        $this->ensureSuperAdmin();
        $this->authorize('settings.manage');

        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(function ($groupSettings) {
                return $groupSettings->map(function (Setting $setting): array {
                    $value = $setting->getTypedValue();
                    $isMasked = in_array($setting->key, Setting::MASKED_KEYS, true)
                        && $value !== null
                        && $value !== '';

                    return [
                        'key' => $setting->key,
                        'value' => $isMasked ? null : $value,
                        'type' => $setting->type,
                        'masked' => $isMasked,
                    ];
                })->values();
            });

        return response()->json($settings);
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
}
