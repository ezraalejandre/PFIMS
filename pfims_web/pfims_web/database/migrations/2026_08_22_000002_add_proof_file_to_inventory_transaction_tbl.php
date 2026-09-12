<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            $table->string('proof_file_path')->nullable()->after('transaction_date');
            $table->string('proof_file_name')->nullable()->after('proof_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            $table->dropColumn(['proof_file_path', 'proof_file_name']);
        });
    }
};
