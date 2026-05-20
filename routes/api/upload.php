<?php

declare(strict_types=1);

use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/upload/logo', [UploadController::class, 'logo']);
    Route::post('/upload/signature', [UploadController::class, 'signature']);
});
