<?php

declare(strict_types=1);

use App\Http\Controllers\DevelopmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/developments', [DevelopmentController::class, 'index']);
    Route::post('/developments', [DevelopmentController::class, 'store']);
    Route::get('/developments/{id}', [DevelopmentController::class, 'show']);
    Route::put('/developments/{id}', [DevelopmentController::class, 'update']);
    Route::delete('/developments/{id}', [DevelopmentController::class, 'destroy']);
    Route::get('/developments/{id}/lots', [DevelopmentController::class, 'lots']);
});
