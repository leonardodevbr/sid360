<?php

declare(strict_types=1);

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::post('/portal/access', [PortalController::class, 'access'])
    ->middleware('throttle:10,1');

Route::middleware('portal.token')->group(function (): void {
    Route::get('/portal/dashboard', [PortalController::class, 'dashboard']);
    Route::post('/portal/logout', [PortalController::class, 'logout']);
});
