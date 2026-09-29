<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fin_expense_category_tbl')->insertOrIgnore([
            ['category_code' => 'OTHERS', 'category_name' => 'Others (SOS, construction bond, etc.)', 'classification' => 'direct', 'is_active' => true],
            ['category_code' => 'ADMINISTRATIVE_EXPENSES', 'category_name' => 'Administrative expenses', 'classification' => 'admin', 'is_active' => true],
        ]);
    }

    public function down(): void
    {
        foreach (['OTHERS', 'ADMINISTRATIVE_EXPENSES'] as $code) {
            $category = DB::table('fin_expense_category_tbl')->where('category_code', $code)->first();
            if ($category && ! DB::table('fin_expense_tbl')->where('fin_category_id', $category->fin_category_id)->exists()) {
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $category->fin_category_id)->delete();
            }
        }
    }
};
