<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ml_project_cost_snapshots')) {
            return;
        }

        Schema::create('ml_project_cost_snapshots', function (Blueprint $table) {
            $table->bigIncrements('snapshot_id');
            $table->integer('project_id');
            $table->dateTime('captured_at', 6);
            $table->string('capture_reason', 32);
            $table->decimal('planned_budget', 14, 2);
            $table->unsignedInteger('planned_duration_months');
            $table->unsignedInteger('worker_count');
            $table->decimal('completion_percentage', 5, 2);
            $table->string('phase', 100)->nullable();
            $table->decimal('elapsed_duration_months', 8, 2);
            $table->date('finance_as_of_date')->nullable();
            $table->decimal('cumulative_total_expense', 14, 2)->default(0);
            $table->decimal('cumulative_material_expense', 14, 2)->default(0);
            $table->decimal('cumulative_labor_expense', 14, 2)->default(0);
            $table->decimal('cumulative_equipment_expense', 14, 2)->default(0);
            $table->decimal('cumulative_other_expense', 14, 2)->default(0);
            $table->decimal('final_actual_cost', 14, 2)->nullable();
            $table->dateTime('finalized_at', 6)->nullable();
            $table->string('data_source', 64)->default('operational');

            $table->index(['project_id', 'captured_at'], 'idx_ml_snapshot_project_time');
            $table->index(['final_actual_cost', 'captured_at'], 'idx_ml_snapshot_finalized_time');
            $table->foreign('project_id', 'fk_ml_snapshot_project')
                ->references('project_id')->on('project_tbl')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_project_cost_snapshots');
    }
};
