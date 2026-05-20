<?php

declare(strict_types=1);

use App\Http\Controllers\ConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/config', [ConfigController::class, 'publicConfig']);
    Route::get('/config/roles', [ConfigController::class, 'roles']);
});
