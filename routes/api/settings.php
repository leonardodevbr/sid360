<?php

declare(strict_types=1);

use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);
    Route::post('/settings/update', [SettingController::class, 'update'])->name('settings.update.post');
});

