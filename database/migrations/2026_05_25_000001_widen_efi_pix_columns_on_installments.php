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
            $table->text('efi_pix_copia_cola')->nullable()->change();
            $table->mediumText('efi_pix_qrcode')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            $table->string('efi_pix_copia_cola', 1000)->nullable()->change();
            $table->string('efi_pix_qrcode', 2000)->nullable()->change();
        });
    }
};
