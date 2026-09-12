<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->integer('inventory_transaction_id')->nullable()->unique()->after('source_expense_id');
            $table->foreign('inventory_transaction_id', 'fk_fin_expense_inventory_transaction')
                ->references('inventory_transaction_id')
                ->on('inventory_transaction_tbl')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->dropForeign('fk_fin_expense_inventory_transaction');
            $table->dropUnique(['inventory_transaction_id']);
            $table->dropColumn('inventory_transaction_id');
        });
    }
};
