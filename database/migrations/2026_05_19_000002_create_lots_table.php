<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_id')->constrained('developments')->cascadeOnDelete();
            $table->string('number');
            $table->string('block')->nullable();
            $table->decimal('area', 12, 2)->nullable();
            $table->decimal('total_value', 14, 2)->nullable();
            $table->string('status')->default('available');
            $table->timestamps();

            $table->unique(['development_id', 'number', 'block']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
