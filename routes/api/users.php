<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users/{id}/update', [UserController::class, 'update']);
    Route::post('/users/{id}/delete', [UserController::class, 'destroy']);
});
