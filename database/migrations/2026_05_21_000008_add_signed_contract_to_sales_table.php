<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('signed_contract_path')->nullable()->after('notes');
            $table->string('signed_contract_original_name')->nullable()->after('signed_contract_path');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['signed_contract_path', 'signed_contract_original_name']);
        });
    }
};
