<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

class ConfigController extends Controller
{
    public function roles(): JsonResponse
    {
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'data' => $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
        ]);
    }

    public function publicConfig(): JsonResponse
    {
        $appName = Settings::get('app_name') ?: Config::get('app.name');

        return response()->json([
            'app_name' => $appName,
            'vapid_public_key' => config('webpush.vapid.public_key') ?: null,
        ]);
    }
}
