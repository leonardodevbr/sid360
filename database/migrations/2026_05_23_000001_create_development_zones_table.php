<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_id')->constrained('developments')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('quadra');
            $table->string('color', 10)->default('#3B82F6');
            $table->json('coordinates')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_zones');
    }
};
