<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Update this part of the database structure.
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->string('proof_file_path')->nullable()->after('remarks');
            $table->string('proof_file_name')->nullable()->after('proof_file_path');
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->dropColumn(['proof_file_path', 'proof_file_name']);
        });
    }
};