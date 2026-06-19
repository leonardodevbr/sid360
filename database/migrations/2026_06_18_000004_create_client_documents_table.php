<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('disk', 20)->default('r2');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'type', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_documents');
    }
};
