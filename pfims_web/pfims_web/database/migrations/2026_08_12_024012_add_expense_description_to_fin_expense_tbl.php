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
            $table->string('expense_description')->nullable()->after('fin_category_id');
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->dropColumn('expense_description');
        });
    }
};