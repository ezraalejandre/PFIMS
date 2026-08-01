<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rental income side of the B.HOE sheets. Backhoe profitability =
     * SUM(rental income) - SUM(fin_equipment_expense_tbl amounts) per
     * asset per month, computed via a view.
     */
    public function up(): void
    {
        Schema::create('fin_equipment_rental_income_tbl', function (Blueprint $table) {
            $table->increments('rental_income_id');
            $table->unsignedInteger('asset_id');
            $table->integer('project_id')->nullable()
                ->comment('Project site that rented/used the equipment');
            $table->date('period_month')->comment('First day of the month, e.g. 2026-05-01');
            $table->decimal('amount', 12, 2);
            $table->string('remarks', 255)->nullable();

            $table->unique(['asset_id', 'project_id', 'period_month'], 'uq_rental_income_asset_project_month');
            $table->foreign('asset_id', 'fk_rental_income_asset_id')
                ->references('asset_id')->on('company_asset_tbl');
            $table->foreign('project_id', 'fk_rental_income_project_id')
                ->references('project_id')->on('project_tbl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_equipment_rental_income_tbl');
    }
};
