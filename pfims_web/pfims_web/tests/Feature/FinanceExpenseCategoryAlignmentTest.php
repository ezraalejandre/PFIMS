<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceExpenseCategoryAlignmentTest extends TestCase
{
    public function test_category_alignment_keeps_existing_expense_links_and_creates_exact_add_choices(): void
    {
        Schema::dropAllTables();
        Schema::create('fin_expense_category_tbl', function (Blueprint $table): void {
            $table->increments('fin_category_id');
            $table->string('category_code')->unique();
            $table->string('category_name');
            $table->string('classification');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('fin_expense_tbl', function (Blueprint $table): void {
            $table->increments('fin_expense_id');
            $table->unsignedInteger('fin_category_id');
            $table->timestamps();
        });
        DB::table('fin_expense_category_tbl')->insert([
            ['fin_category_id' => 1, 'category_code' => 'EQUIPMENT_RENTAL', 'category_name' => 'Transportation', 'classification' => 'direct'],
            ['fin_category_id' => 2, 'category_code' => 'ADMIN_SALARIES', 'category_name' => 'Office Salaries', 'classification' => 'office'],
            ['fin_category_id' => 3, 'category_code' => 'OFFICE_EXPENSES', 'category_name' => 'Office Expenses', 'classification' => 'office'],
            ['fin_category_id' => 4, 'category_code' => 'SSS_PHILHEALTH', 'category_name' => 'Employer contributions', 'classification' => 'admin'],
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['fin_expense_id' => 10, 'fin_category_id' => 1],
            ['fin_expense_id' => 11, 'fin_category_id' => 2],
            ['fin_expense_id' => 12, 'fin_category_id' => 3],
            ['fin_expense_id' => 13, 'fin_category_id' => 4],
        ]);

        $migration = require database_path('migrations/2026_09_30_000000_align_finance_expense_categories.php');
        $migration->up();

        $this->assertDatabaseHas('fin_expense_category_tbl', ['fin_category_id' => 1, 'category_code' => 'TRANSPORTATION_EXPENSES', 'category_name' => 'Transportation Expenses', 'classification' => 'direct']);
        $this->assertDatabaseHas('fin_expense_category_tbl', ['fin_category_id' => 2, 'category_code' => 'ADMIN_SALARIES_WAGES', 'category_name' => 'Salaries & Wages', 'classification' => 'admin']);
        $this->assertDatabaseHas('fin_expense_category_tbl', ['fin_category_id' => 3, 'category_code' => 'OFFICE_EXPENSES']);
        $this->assertDatabaseHas('fin_expense_tbl', ['fin_expense_id' => 10, 'fin_category_id' => 1]);
        $this->assertDatabaseHas('fin_expense_tbl', ['fin_expense_id' => 11, 'fin_category_id' => 2]);
        $this->assertDatabaseHas('fin_expense_tbl', ['fin_expense_id' => 12, 'fin_category_id' => 3]);
        $this->assertDatabaseMissing('fin_expense_category_tbl', ['category_code' => 'SSS_PHILHEALTH']);
        $this->assertDatabaseHas('fin_expense_tbl', [
            'fin_expense_id' => 13,
            'fin_category_id' => DB::table('fin_expense_category_tbl')->where('category_code', 'ADMIN_SSS_PHILHEALTH')->value('fin_category_id'),
        ]);
        $this->assertDatabaseCount('fin_expense_category_tbl', 21);

        $replacement = require database_path('migrations/2026_09_30_010000_replace_finance_expense_categories.php');
        $replacement->up();

        $this->assertDatabaseCount('fin_expense_category_tbl', 20);
        $this->assertDatabaseHas('fin_expense_category_tbl', ['category_code' => 'RENT_EXPENSE', 'classification' => 'admin']);
        $this->assertDatabaseHas('fin_expense_category_tbl', ['category_code' => 'OTHERS_SOS_ETC', 'classification' => 'direct']);
        $this->assertDatabaseMissing('fin_expense_category_tbl', ['category_code' => 'OFFICE_EXPENSES']);
        $this->assertDatabaseHas('fin_expense_tbl', [
            'fin_expense_id' => 12,
            'fin_category_id' => DB::table('fin_expense_category_tbl')->where('category_code', 'MISCELLANEOUS_EXPENSE')->value('fin_category_id'),
        ]);
        $this->assertSame(0, DB::table('fin_expense_tbl as expense')
            ->leftJoin('fin_expense_category_tbl as category', 'category.fin_category_id', '=', 'expense.fin_category_id')
            ->whereNull('category.fin_category_id')->count());
    }
}
