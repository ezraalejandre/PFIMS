<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // expense_tbl is a legacy table and is not created by the current
        // schema. Keep historical installations upgradeable without blocking
        // a clean install when the table is absent.
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        Schema::table('expense_tbl', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_tbl', 'equipment_amount')) {
                $table->decimal('equipment_amount', 12, 2)->nullable()->after('material_amount');
            }
            if (! Schema::hasColumn('expense_tbl', 'other_amount')) {
                $table->decimal('other_amount', 12, 2)->nullable()->after('equipment_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        Schema::table('expense_tbl', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('expense_tbl', 'equipment_amount') ? 'equipment_amount' : null,
                Schema::hasColumn('expense_tbl', 'other_amount') ? 'other_amount' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
