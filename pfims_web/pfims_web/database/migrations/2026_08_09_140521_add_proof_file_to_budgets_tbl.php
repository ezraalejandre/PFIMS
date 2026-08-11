<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budgets_tbl', function (Blueprint $table) {
            $table->string('proof_file_path')->nullable()->after('actual_amount');
            $table->string('proof_file_name')->nullable()->after('proof_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets_tbl', function (Blueprint $table) {
            $table->dropColumn(['proof_file_path', 'proof_file_name']);
        });
    }
};