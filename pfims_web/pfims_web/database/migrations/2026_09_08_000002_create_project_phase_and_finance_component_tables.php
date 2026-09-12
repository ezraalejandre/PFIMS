<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_phase_tbl')) {
            Schema::create('project_phase_tbl', function (Blueprint $table) {
                $table->increments('phase_id');
                $table->string('phase_name', 100)->unique();
            });
        }
        if (! Schema::hasTable('fin_component_tbl')) {
            Schema::create('fin_component_tbl', function (Blueprint $table) {
                $table->increments('component_id');
                $table->string('component_name', 100)->unique();
            });
        }

        if (Schema::hasTable('project_tbl')) {
            DB::table('project_tbl')->whereNotNull('phase')->where('phase', '!=', '')->distinct()
                ->orderBy('phase')->pluck('phase')->each(fn ($name) => DB::table('project_phase_tbl')->insertOrIgnore(['phase_name' => trim((string) $name)]));
        }
        if (Schema::hasTable('fin_expense_tbl')) {
            DB::table('fin_expense_tbl')->whereNotNull('project_cost_component')->where('project_cost_component', '!=', '')->distinct()
                ->orderBy('project_cost_component')->pluck('project_cost_component')->each(fn ($name) => DB::table('fin_component_tbl')->insertOrIgnore(['component_name' => trim((string) $name)]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_component_tbl');
        Schema::dropIfExists('project_phase_tbl');
    }
};
