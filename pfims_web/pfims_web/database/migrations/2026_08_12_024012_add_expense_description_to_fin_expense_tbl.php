<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->string('expense_description')->nullable()->after('fin_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->dropColumn('expense_description');
        });
    }
};