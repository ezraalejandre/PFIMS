<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'direct_expense_count_7d', 'direct_expense_count_30d',
        'stock_out_count_7d', 'stock_out_count_30d',
        'unvalued_stock_out_count',
    ];

    public function up(): void
    {
        Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
            $table->unsignedInteger('direct_expense_count_7d')->nullable();
            $table->unsignedInteger('direct_expense_count_30d')->nullable();
            $table->decimal('direct_expense_amount_30d', 14, 2)->nullable();
            $table->unsignedInteger('stock_out_count_7d')->nullable();
            $table->unsignedInteger('stock_out_count_30d')->nullable();
            $table->decimal('stock_out_quantity_30d', 14, 2)->nullable();
            $table->decimal('valued_stock_out_cost', 14, 2)->nullable();
            $table->unsignedInteger('unvalued_stock_out_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
            $table->dropColumn([...self::COLUMNS,
                'direct_expense_amount_30d', 'stock_out_quantity_30d', 'valued_stock_out_cost',
            ]);
        });
    }
};
