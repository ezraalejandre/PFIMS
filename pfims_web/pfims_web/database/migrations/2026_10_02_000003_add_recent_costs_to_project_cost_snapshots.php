<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
            // Unknown historical windows stay null; never rebuild them from the final ledger.
            $table->decimal('direct_expense_amount_7d', 14, 2)->nullable();
            $table->decimal('valued_stock_out_cost_7d', 14, 2)->nullable();
            $table->decimal('valued_stock_out_cost_30d', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
            $table->dropColumn(['direct_expense_amount_7d', 'valued_stock_out_cost_7d', 'valued_stock_out_cost_30d']);
        });
    }
};
