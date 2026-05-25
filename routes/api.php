<?php

declare(strict_types=1);

use App\Http\Controllers\EfiPaymentController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::post('/efi/webhook/pix', [EfiPaymentController::class, 'pixWebhook']);
Route::post('/efi/webhook/cobrancas', [EfiPaymentController::class, 'cobrancasWebhook']);
Route::get('/efi/webhook/pix', fn () => response()->json(['ok' => true]));

require __DIR__.'/api/auth.php';
require __DIR__.'/api/users.php';
require __DIR__.'/api/config.php';
require __DIR__.'/api/settings.php';
require __DIR__.'/api/upload.php';
require __DIR__.'/api/developments.php';
require __DIR__.'/api/lots.php';
require __DIR__.'/api/dashboard.php';
require __DIR__.'/api/clients.php';
require __DIR__.'/api/sales.php';
require __DIR__.'/api/whatsapp.php';
require __DIR__.'/api/portal.php';

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::post('/push/test', [PushSubscriptionController::class, 'test']);
});
