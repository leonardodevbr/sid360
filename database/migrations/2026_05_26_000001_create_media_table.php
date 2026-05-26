<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->morphs('mediable');
            $table->string('disk')->default('gcs');
            $table->string('path');
            $table->string('url');
            $table->string('filename');
            $table->string('mime_type', 100);
            $table->string('type', 30)->default('photo');
            $table->unsignedBigInteger('size')->default(0);
            $table->integer('order')->default(0);
            $table->string('caption')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'type']);
            $table->index(['mediable_type', 'mediable_id', 'is_cover']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
