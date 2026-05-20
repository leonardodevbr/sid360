<?php

declare(strict_types=1);

use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::put('/sales/{id}', [SaleController::class, 'update']);
    Route::delete('/sales/{id}', [SaleController::class, 'destroy']);
    Route::get('/sales/{id}/contract', [SaleController::class, 'contract']);
    Route::post('/sales/{id}/signed-contract', [SaleController::class, 'uploadSignedContract']);
    Route::get('/sales/{id}/signed-contract', [SaleController::class, 'signedContract']);
    Route::get('/sales/{id}/installments', [InstallmentController::class, 'bySale']);
    Route::post('/installments/{id}/pay', [InstallmentController::class, 'pay']);
});
