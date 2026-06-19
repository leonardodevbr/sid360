<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('client_document_id')->nullable()->constrained('client_documents')->nullOnDelete();
            $table->string('type', 40);
            $table->string('disk', 20)->default('r2');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_documents');
    }
};
