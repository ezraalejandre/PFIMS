<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per asset per expense entry (REPAIR sheet's per-vehicle
     * amounts and the B.HOE sheets' per-category amounts — Gas/Diesel,
     * Payroll, Repair, Other, Delivery, Transpo — both reduce to
     * asset x expense_type x date instead of one column per asset).
     */
    public function up(): void
    {
        Schema::create('fin_equipment_expense_tbl', function (Blueprint $table) {
            $table->increments('equip_expense_id');
            $table->unsignedInteger('asset_id');
            $table->integer('project_id')->nullable()
                ->comment('Site where the asset was deployed; NULL if company-wide/shop expense');
            $table->enum('expense_type', [
                'gas_diesel',
                'payroll_operator',
                'repair',
                'delivery',
                'transportation',
                'other',
            ]);
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('remarks', 255)->nullable();

            $table->foreign('asset_id', 'fk_equip_expense_asset_id')
                ->references('asset_id')->on('company_asset_tbl');
            $table->foreign('project_id', 'fk_equip_expense_project_id')
                ->references('project_id')->on('project_tbl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_equipment_expense_tbl');
    }
};
