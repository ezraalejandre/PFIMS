<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Contract & payment terms per project (PRFTDIRECT / PROFIT
     * sheets, contract side — the expense side is read from
     * fin_expense_tbl). `project_tbl.start_date` / `actual_end_date`
     * are reused as-is for "DATE STARTED" / "DATE FINISHED" instead of
     * duplicating them here.
     *
     * Total Contract Price = original_contract_price + additional_works_contract
     * Total Payment        = original_payment_received + additional_works_payment
     * Both profit/loss bases (payment vs. contract, direct vs. overall
     * expense) are computed via views, not stored.
     */
    public function up(): void
    {
        Schema::create('fin_project_contract_tbl', function (Blueprint $table) {
            $table->increments('contract_id');
            $table->integer('project_id');
            $table->decimal('original_contract_price', 14, 2)->default(0.00);
            $table->decimal('additional_works_contract', 14, 2)->default(0.00)
                ->comment('Additional works added to the CONTRACT price');
            $table->decimal('original_payment_received', 14, 2)->default(0.00);
            $table->decimal('additional_works_payment', 14, 2)->default(0.00)
                ->comment('Additional works PAID/received');
            $table->string('remarks', 255)->nullable();

            $table->unique('project_id', 'uq_contract_project');
            $table->foreign('project_id', 'fk_contract_project_id')
                ->references('project_id')->on('project_tbl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_project_contract_tbl');
    }
};
