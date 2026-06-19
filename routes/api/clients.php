<?php

declare(strict_types=1);

use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::post('/clients/{id}/update', [ClientController::class, 'update']);
    Route::post('/clients/{id}/delete', [ClientController::class, 'destroy']);
    Route::post('/clients/{id}/whatsapp-status', [ClientController::class, 'updateWhatsappStatus']);
    Route::get('/clients/{id}/documents', [ClientController::class, 'documents']);
    Route::post('/clients/{id}/documents', [ClientController::class, 'uploadDocument']);
    Route::get('/clients/{id}/documents/{documentId}/download', [ClientController::class, 'downloadDocument']);
    Route::get('/clients/{id}/documents/{documentId}/preview', [ClientController::class, 'previewDocument']);
    Route::post('/clients/{id}/documents/{documentId}/delete', [ClientController::class, 'deleteDocument']);
});
