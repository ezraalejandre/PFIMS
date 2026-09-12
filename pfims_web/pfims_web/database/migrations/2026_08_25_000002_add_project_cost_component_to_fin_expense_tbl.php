<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fin_expense_tbl', 'project_cost_component')) {
            Schema::table('fin_expense_tbl', function (Blueprint $table) {
                $table->string('project_cost_component', 20)->nullable()->after('project_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fin_expense_tbl', 'project_cost_component')) {
            Schema::table('fin_expense_tbl', function (Blueprint $table) {
                $table->dropColumn('project_cost_component');
            });
        }
    }
};
