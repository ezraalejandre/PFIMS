<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // expense_tbl is a legacy table and is not created by the current
        // schema. Keep historical installations upgradeable without blocking
        // a clean install when the table is absent.
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        // Update this part of the database structure.
        Schema::table('expense_tbl', function (Blueprint $table) {
            // Run this step if the value check matches.
            if (! Schema::hasColumn('expense_tbl', 'equipment_amount')) {
                $table->decimal('equipment_amount', 12, 2)->nullable()->after('material_amount');
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('expense_tbl', 'other_amount')) {
                $table->decimal('other_amount', 12, 2)->nullable()->after('equipment_amount');
            }
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Run this step if the value check matches.
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        // Update this part of the database structure.
        Schema::table('expense_tbl', function (Blueprint $table) {
            $columns = array_values(array_filter([
                // Update this part of the database structure.
                Schema::hasColumn('expense_tbl', 'equipment_amount') ? 'equipment_amount' : null,
                // Update this part of the database structure.
                Schema::hasColumn('expense_tbl', 'other_amount') ? 'other_amount' : null,
            ]));
            // Run this step if the columns is not empty.
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
