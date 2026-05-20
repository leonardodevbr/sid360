<?php

declare(strict_types=1);

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

require __DIR__.'/api/auth.php';
require __DIR__.'/api/users.php';
require __DIR__.'/api/config.php';
require __DIR__.'/api/settings.php';
require __DIR__.'/api/upload.php';
require __DIR__.'/api/developments.php';
require __DIR__.'/api/lots.php';
require __DIR__.'/api/dashboard.php';

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::post('/push/test', [PushSubscriptionController::class, 'test']);
});
