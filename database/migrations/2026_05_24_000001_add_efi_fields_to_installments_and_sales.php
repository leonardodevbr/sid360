<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            $table->string('efi_charge_id')->nullable()->after('status');
            $table->string('efi_txid')->nullable()->after('efi_charge_id');
            $table->string('efi_barcode')->nullable()->after('efi_txid');
            $table->string('efi_pdf_url')->nullable()->after('efi_barcode');
            $table->text('efi_pix_copia_cola')->nullable()->after('efi_pdf_url');
            $table->mediumText('efi_pix_qrcode')->nullable()->after('efi_pix_copia_cola');
            $table->string('efi_payment_type')->nullable()->after('efi_pix_qrcode');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->integer('efi_carnet_id')->nullable()->after('status');
            $table->string('efi_carnet_pdf')->nullable()->after('efi_carnet_id');
            $table->string('efi_carnet_link')->nullable()->after('efi_carnet_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            $table->dropColumn([
                'efi_charge_id',
                'efi_txid',
                'efi_barcode',
                'efi_pdf_url',
                'efi_pix_copia_cola',
                'efi_pix_qrcode',
                'efi_payment_type',
            ]);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn([
                'efi_carnet_id',
                'efi_carnet_pdf',
                'efi_carnet_link',
            ]);
        });
    }
};
