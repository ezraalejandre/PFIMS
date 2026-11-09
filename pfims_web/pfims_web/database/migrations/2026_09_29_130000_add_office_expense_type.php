<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Update this part of the database structure.
        Schema::table('fin_expense_category_tbl', function (Blueprint $table): void {
            $table->enum('classification', ['direct', 'admin', 'office'])->change();
        });

        DB::table('fin_expense_category_tbl')->insertOrIgnore([
            'category_code' => 'OFFICE_EXPENSES',
            'category_name' => 'Office Expenses',
            'classification' => 'office',
            'is_active' => true,
        ]);
        DB::table('fin_expense_category_tbl')
            ->whereIn('category_code', ['OFFICE_RENT', 'ADMIN_SALARIES', 'OFFICE_SALARIES', 'OFFICE_EXPENSES'])
            ->update(['classification' => 'office']);
    }

    // Undo these database changes.
    public function down(): void
    {
        DB::table('fin_expense_category_tbl')
            ->whereIn('category_code', ['OFFICE_RENT', 'ADMIN_SALARIES', 'OFFICE_SALARIES', 'OFFICE_EXPENSES'])
            ->update(['classification' => 'admin']);
        // Update this part of the database structure.
        Schema::table('fin_expense_category_tbl', function (Blueprint $table): void {
            $table->enum('classification', ['direct', 'admin'])->change();
        });
    }
};
