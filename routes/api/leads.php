<?php

declare(strict_types=1);

use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/{id}', [LeadController::class, 'show']);
    Route::patch('/leads/{id}/status', [LeadController::class, 'updateStatus']);
    Route::post('/leads/{id}/convert', [LeadController::class, 'convertToSale']);
    Route::delete('/leads/{id}', [LeadController::class, 'destroy']);
});
