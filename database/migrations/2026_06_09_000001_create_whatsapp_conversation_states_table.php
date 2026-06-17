<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversation_states', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('status', 32)->default('bot_active');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('human_until')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('client_id');
            $table->index('human_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_states');
    }
};
