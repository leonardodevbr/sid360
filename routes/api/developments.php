<?php

declare(strict_types=1);

use App\Http\Controllers\DevelopmentController;
use App\Http\Controllers\DevelopmentStreetController;
use App\Http\Controllers\DevelopmentZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/developments', [DevelopmentController::class, 'index']);
    Route::post('/developments', [DevelopmentController::class, 'store']);
    Route::get('/developments/{id}', [DevelopmentController::class, 'show']);
    Route::put('/developments/{id}', [DevelopmentController::class, 'update']);
    Route::delete('/developments/{id}', [DevelopmentController::class, 'destroy']);
    Route::get('/developments/{id}/lots', [DevelopmentController::class, 'lots']);

    Route::get('/developments/{id}/zones', [DevelopmentZoneController::class, 'index']);
    Route::post('/developments/{id}/zones', [DevelopmentZoneController::class, 'store']);
    Route::put('/developments/{id}/zones/{zoneId}', [DevelopmentZoneController::class, 'update']);
    Route::delete('/developments/{id}/zones/{zoneId}', [DevelopmentZoneController::class, 'destroy']);
    Route::post('/developments/{id}/zones/{zoneId}/generate-lots', [DevelopmentZoneController::class, 'generateLots']);

    Route::get('/developments/{id}/streets', [DevelopmentStreetController::class, 'index']);
    Route::post('/developments/{id}/streets', [DevelopmentStreetController::class, 'store']);
    Route::put('/developments/{id}/streets/{streetId}', [DevelopmentStreetController::class, 'update']);
    Route::delete('/developments/{id}/streets/{streetId}', [DevelopmentStreetController::class, 'destroy']);
});
