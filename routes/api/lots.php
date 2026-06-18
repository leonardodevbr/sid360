<?php

declare(strict_types=1);

use App\Http\Controllers\LotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/lots', [LotController::class, 'index']);
    Route::post('/lots', [LotController::class, 'store']);
    Route::post('/lots/bulk-delete', [LotController::class, 'bulkDestroy']);
    Route::post('/lots/bulk-update', [LotController::class, 'bulkUpdate']);
    Route::post('/lots/bulk-update-status', [LotController::class, 'bulkUpdateStatus']);
    Route::get('/lots/{id}', [LotController::class, 'show']);
    Route::post('/lots/{id}/update', [LotController::class, 'update']);
    Route::post('/lots/{id}/delete', [LotController::class, 'destroy']);
});
