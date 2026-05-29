<?php

declare(strict_types=1);

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/lots/{id}/media', [MediaController::class, 'indexLot']);
    Route::post('/lots/{id}/media', [MediaController::class, 'uploadLot']);

    Route::get('/developments/{id}/media', [MediaController::class, 'indexDevelopment']);
    Route::post('/developments/{id}/media', [MediaController::class, 'uploadDevelopment']);

    Route::post('/media/{id}/update', [MediaController::class, 'update']);
    Route::post('/media/{id}/delete', [MediaController::class, 'destroy']);
    Route::post('/media/{id}/cover', [MediaController::class, 'setCover']);
    Route::post('/media/reorder', [MediaController::class, 'reorder']);
});
