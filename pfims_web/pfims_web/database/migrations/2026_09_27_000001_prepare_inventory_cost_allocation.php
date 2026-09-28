<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            // Historical movements remain unclassified until explicitly reconciled.
            $table->string('movement_reason', 32)->nullable();
            $table->dateTime('recorded_at', 6)->nullable();
        });

        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            // The linked Finance amount is the sole purchase price, not a second inventory price.
            $table->string('entry_kind', 32)->nullable();
        });

        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('in_transaction_id');
            $table->integer('out_transaction_id');
            $table->integer('project_id');
            $table->decimal('quantity', 10, 2);
            $table->string('valuation_status', 16);
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->decimal('allocated_amount', 14, 2)->nullable();
            $table->dateTime('allocated_at', 6);

            $table->unique(['in_transaction_id', 'out_transaction_id'], 'uq_inventory_cost_in_out');
            $table->index('out_transaction_id', 'idx_inventory_cost_out');
            $table->index('project_id', 'idx_inventory_cost_project');
            $table->foreign('in_transaction_id', 'fk_inventory_cost_in')
                ->references('inventory_transaction_id')->on('inventory_transaction_tbl')->restrictOnDelete();
            $table->foreign('out_transaction_id', 'fk_inventory_cost_out')
                ->references('inventory_transaction_id')->on('inventory_transaction_tbl')->restrictOnDelete();
            $table->foreign('project_id', 'fk_inventory_cost_project')
                ->references('project_id')->on('project_tbl')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_allocation_tbl');

        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->dropColumn('entry_kind');
        });

        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            $table->dropColumn(['movement_reason', 'recorded_at']);
        });
    }
};
