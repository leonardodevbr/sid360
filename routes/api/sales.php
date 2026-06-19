<?php

declare(strict_types=1);

use App\Http\Controllers\EfiPaymentController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\SaleController;
use App\Http\Resources\InstallmentInteractionResource;
use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{id}', [SaleController::class, 'show']);
    Route::post('/sales/{id}/update', [SaleController::class, 'update']);
    Route::post('/sales/{id}/delete', [SaleController::class, 'destroy']);
    Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);
    Route::post('/sales/{id}/whatsapp/overdue', [SaleController::class, 'sendOverdueWhatsapp']);
    Route::get('/sales/{id}/contract', [SaleController::class, 'contract']);
    Route::get('/sales/{id}/contract/preview', [SaleController::class, 'contractPreview']);
    Route::get('/sales/{id}/carne', [SaleController::class, 'carne']);
    Route::get('/sales/{id}/carne/preview', [SaleController::class, 'carnePreviewHtml']);
    Route::post('/sales/{id}/signed-contract', [SaleController::class, 'uploadSignedContract']);
    Route::get('/sales/{id}/signed-contract', [SaleController::class, 'signedContract']);
    Route::get('/sales/{id}/documents', [SaleController::class, 'documents']);
    Route::post('/sales/{id}/documents', [SaleController::class, 'uploadDocument']);
    Route::get('/sales/{id}/documents/{documentId}/download', [SaleController::class, 'downloadDocument']);
    Route::get('/sales/{id}/documents/{documentId}/preview', [SaleController::class, 'previewDocument']);
    Route::get('/sales/{id}/installments', [InstallmentController::class, 'bySale']);
    Route::get('/sales/{id}/interactions', function (Request $request, string|int $id) {
        $sale = Sale::query()->findOrFail((int) $id);
        $interactions = InstallmentInteraction::query()
            ->where('sale_id', $sale->id)
            ->with('installment')
            ->latest()
            ->limit(50)
            ->get();

        $referencedIds = $interactions
            ->flatMap(function (InstallmentInteraction $interaction): array {
                return array_merge(
                    $interaction->installment_id ? [$interaction->installment_id] : [],
                    is_array($interaction->meta['installment_ids'] ?? null)
                        ? $interaction->meta['installment_ids']
                        : [],
                );
            })
            ->unique()
            ->values()
            ->all();

        $request->attributes->set(
            'installments_by_id',
            Installment::query()->whereIn('id', $referencedIds)->get()->keyBy('id'),
        );

        return InstallmentInteractionResource::collection($interactions);
    });
    Route::post('/installments/{id}/pay', [InstallmentController::class, 'pay']);
    Route::post('/installments/{id}/due-date', [InstallmentController::class, 'updateDueDate']);
    Route::get('/installments/{id}/recibo', [InstallmentController::class, 'recibo']);
    Route::post('/installments/{id}/recibo/whatsapp', [InstallmentController::class, 'sendReciboWhatsApp']);
    Route::get('/sales/{id}/efi/carne-preview', [EfiPaymentController::class, 'previewCarneDebtor']);
    Route::post('/sales/{id}/efi/carne', [EfiPaymentController::class, 'generateCarne']);
    Route::get('/installments/{id}/efi/charge-preview', [EfiPaymentController::class, 'chargePreview']);
    Route::post('/installments/{id}/efi/pix', [EfiPaymentController::class, 'generatePix']);
    Route::post('/installments/{id}/efi/pix/whatsapp', [EfiPaymentController::class, 'sendPixWhatsApp']);
    Route::post('/installments/{id}/efi/boleto', [EfiPaymentController::class, 'generateBoleto']);
    Route::post('/installments/{id}/efi/boleto/whatsapp', [EfiPaymentController::class, 'sendBoletoWhatsApp']);
});
