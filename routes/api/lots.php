<?php

declare(strict_types=1);

use App\Http\Controllers\LotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/lots', [LotController::class, 'index']);
    Route::post('/lots', [LotController::class, 'store']);
    Route::get('/lots/{id}', [LotController::class, 'show']);
    Route::put('/lots/{id}', [LotController::class, 'update']);
    Route::delete('/lots/{id}', [LotController::class, 'destroy']);
});
