<?php

declare(strict_types=1);

use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\SaleController;
use App\Http\Resources\InstallmentInteractionResource;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::put('/sales/{id}', [SaleController::class, 'update']);
    Route::delete('/sales/{id}', [SaleController::class, 'destroy']);
    Route::get('/sales/{id}/contract', [SaleController::class, 'contract']);
    Route::get('/sales/{id}/carne', [SaleController::class, 'carne']);
    Route::post('/sales/{id}/signed-contract', [SaleController::class, 'uploadSignedContract']);
    Route::get('/sales/{id}/signed-contract', [SaleController::class, 'signedContract']);
    Route::get('/sales/{id}/installments', [InstallmentController::class, 'bySale']);
    Route::get('/sales/{id}/interactions', function (string|int $id) {
        $sale = Sale::query()->findOrFail((int) $id);
        $interactions = InstallmentInteraction::query()
            ->where('sale_id', $sale->id)
            ->latest()
            ->limit(50)
            ->get();

        return InstallmentInteractionResource::collection($interactions);
    });
    Route::post('/installments/{id}/pay', [InstallmentController::class, 'pay']);
});
