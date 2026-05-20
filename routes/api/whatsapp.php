<?php

declare(strict_types=1);

use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/whatsapp/check/{phone}', [WhatsappController::class, 'check']);
    Route::post('/whatsapp/send-otp', [WhatsappController::class, 'sendOtp']);
    Route::post('/whatsapp/verify-otp', [WhatsappController::class, 'verifyOtp']);
});
