<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Run this step if the value check matches.
        if (! Schema::hasColumn('fin_expense_tbl', 'project_cost_component')) {
            // Update this part of the database structure.
            Schema::table('fin_expense_tbl', function (Blueprint $table) {
                $table->string('project_cost_component', 20)->nullable()->after('project_id');
            });
        }
    }

    // Undo these database changes.
    public function down(): void
    {
        // Run this step if the value check matches.
        if (Schema::hasColumn('fin_expense_tbl', 'project_cost_component')) {
            // Update this part of the database structure.
            Schema::table('fin_expense_tbl', function (Blueprint $table) {
                $table->dropColumn('project_cost_component');
            });
        }
    }
};
