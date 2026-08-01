<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One balance per account per month (CASHASSET sheet) instead of
     * one column per month. R Total (per month, all accounts) =
     * SUM(balance_amount) WHERE period_month = X, computed via a view.
     */
    public function up(): void
    {
        Schema::create('fin_cash_position_tbl', function (Blueprint $table) {
            $table->increments('cash_position_id');
            $table->unsignedInteger('account_id');
            $table->date('period_month')->comment('First day of month, e.g. 2026-05-01');
            $table->decimal('balance_amount', 14, 2);

            $table->unique(['account_id', 'period_month'], 'uq_cash_position_account_month');
            $table->foreign('account_id', 'fk_cash_position_account_id')
                ->references('account_id')->on('company_bank_account_tbl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_cash_position_tbl');
    }
};
