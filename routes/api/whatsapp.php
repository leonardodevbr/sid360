<?php

declare(strict_types=1);

use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'handle'])
    ->middleware(['whatsapp.webhook', 'throttle:120,1']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/whatsapp/check/{phone}', [WhatsappController::class, 'check']);
    Route::post('/whatsapp/send-otp', [WhatsappController::class, 'sendOtp']);
    Route::post('/whatsapp/verify-otp', [WhatsappController::class, 'verifyOtp']);
});
