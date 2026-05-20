<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/users/{id}/update', [UserController::class, 'update'])->name('users.update.post');
    Route::apiResource('users', UserController::class);
});
