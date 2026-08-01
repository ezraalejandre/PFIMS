<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Central finance ledger fact table. Powers EXPOVRALL, EXP DIRECT,
     * DIRECT EXP, ADMINEXP, OVERALLEXP, and SUMMARYEXP via
     * project x category x date aggregation instead of six separate
     * duplicate tables. `project_id` NULL = office/admin-level entry
     * (the "OFFICE" row in EXPOVRALL/ADMINEXP). `source_expense_id`
     * optionally links back to the existing `expense_tbl` row so a
     * site expense already logged operationally doesn't need to be
     * re-keyed for finance classification.
     */
    public function up(): void
    {
        Schema::create('fin_expense_tbl', function (Blueprint $table) {
            $table->increments('fin_expense_id');
            $table->integer('project_id')->nullable();
            $table->unsignedInteger('fin_category_id');
            $table->integer('source_expense_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->foreign('project_id', 'fk_fin_expense_project_id')
                ->references('project_id')->on('project_tbl');
            $table->foreign('fin_category_id', 'fk_fin_expense_category_id')
                ->references('fin_category_id')->on('fin_expense_category_tbl');
            $table->foreign('source_expense_id', 'fk_fin_expense_source_expense_id')
                ->references('expense_id')->on('expense_tbl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_expense_tbl');
    }
};
