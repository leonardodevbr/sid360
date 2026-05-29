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
    Route::post('/developments/{id}/update', [DevelopmentController::class, 'update']);
    Route::post('/developments/{id}/delete', [DevelopmentController::class, 'destroy']);
    Route::get('/developments/{id}/lots', [DevelopmentController::class, 'lots']);

    Route::get('/developments/{id}/zones', [DevelopmentZoneController::class, 'index']);
    Route::post('/developments/{id}/zones', [DevelopmentZoneController::class, 'store']);
    Route::post('/developments/{id}/zones/{zoneId}/update', [DevelopmentZoneController::class, 'update']);
    Route::post('/developments/{id}/zones/{zoneId}/delete', [DevelopmentZoneController::class, 'destroy']);
    Route::post('/developments/{id}/zones/{zoneId}/generate-lots', [DevelopmentZoneController::class, 'generateLots']);

    Route::get('/developments/{id}/streets', [DevelopmentStreetController::class, 'index']);
    Route::post('/developments/{id}/streets', [DevelopmentStreetController::class, 'store']);
    Route::post('/developments/{id}/streets/{streetId}/update', [DevelopmentStreetController::class, 'update']);
    Route::post('/developments/{id}/streets/{streetId}/delete', [DevelopmentStreetController::class, 'destroy']);
});
