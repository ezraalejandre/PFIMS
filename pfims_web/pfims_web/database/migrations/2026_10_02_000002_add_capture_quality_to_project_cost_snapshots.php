<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Update this part of the database structure.
        Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
            // Leave historical observations unknown; do not rebuild their schedules or quality.
            $table->unsignedSmallInteger('capture_schema_version')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->unsignedInteger('planned_duration_days')->nullable();
            $table->unsignedInteger('elapsed_days')->nullable();
            $table->unsignedInteger('remaining_planned_days')->nullable();
            $table->unsignedInteger('days_past_planned_end')->nullable();
            $table->boolean('cost_coverage_complete')->nullable();
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
            $table->dropColumn(['capture_schema_version', 'planned_start_date', 'planned_end_date',
                'planned_duration_days', 'elapsed_days', 'remaining_planned_days',
                'days_past_planned_end', 'cost_coverage_complete']);
        });
    }
};
