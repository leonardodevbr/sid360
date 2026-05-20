<?php

declare(strict_types=1);

use App\Http\Controllers\LoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/lotes', [LoteController::class, 'index']);
    Route::post('/lotes', [LoteController::class, 'store']);
    Route::get('/lotes/{id}', [LoteController::class, 'show']);
    Route::put('/lotes/{id}', [LoteController::class, 'update']);
    Route::delete('/lotes/{id}', [LoteController::class, 'destroy']);
});
