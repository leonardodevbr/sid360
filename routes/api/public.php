<?php

declare(strict_types=1);

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function (): void {
    Route::get('/developments', [PublicController::class, 'developments']);
    Route::get('/developments/{slug}', [PublicController::class, 'development']);
    Route::get('/developments/{devId}/lots/{lotId}', [PublicController::class, 'lot']);
    Route::get('/lots/available', [PublicController::class, 'lotsAvailable']);
    Route::post('/leads', [PublicController::class, 'submitLead'])
        ->middleware('throttle:10,1');
});
