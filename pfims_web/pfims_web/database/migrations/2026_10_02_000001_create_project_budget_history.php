<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Run this step if the value check matches.
        if (! Schema::hasTable('project_budget_history')) {
            // Update this part of the database structure.
            Schema::create('project_budget_history', function (Blueprint $table) {
                $table->bigIncrements('history_id');
                $table->integer('project_id');
                $table->unsignedInteger('budget_id');
                $table->decimal('budget_amount', 14, 2)->nullable();
                $table->string('event_type', 32);
                $table->dateTime('effective_at', 6)->nullable();
                $table->dateTime('recorded_at', 6);
                $table->string('reason', 500);
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->index(['project_id', 'history_id'], 'idx_budget_history_project');
                $table->index(['budget_id', 'history_id'], 'idx_budget_history_budget');
                // Budget IDs remain as evidence after deletion or reassignment.
                $table->foreign('project_id')->references('project_id')->on('project_tbl')->cascadeOnDelete();
            });
        }

        $recordedAt = now();
        // Process the records in smaller groups.
        DB::table('budgets_tbl')->orderBy('budget_id')->chunkById(200, function ($budgets) use ($recordedAt) {
            // Process each budget.
            foreach ($budgets as $budget) {
                // Skip this item if the project id and budget id checks match.
                if (! DB::table('project_tbl')->where('project_id', $budget->project_id)->exists()
                    || DB::table('project_budget_history')->where('budget_id', $budget->budget_id)
                        ->where('project_id', $budget->project_id)->exists()) {
                    continue;
                }
                DB::table('project_budget_history')->insert([
                    'project_id' => $budget->project_id,
                    'budget_id' => $budget->budget_id,
                    'budget_amount' => $budget->budget_amount,
                    'event_type' => 'opening_observed',
                    'effective_at' => null,
                    'recorded_at' => $recordedAt,
                    'reason' => 'Existing budget first observed; original approval date and amount are unknown.',
                    'recorded_by' => null,
                ]);
            }
        }, 'budget_id');

        // Run this step if the input checks match.
        if (Schema::hasTable('ml_project_cost_snapshots') && ! Schema::hasColumn('ml_project_cost_snapshots', 'budget_history_id')) {
            // Update this part of the database structure.
            Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
                $table->unsignedBigInteger('budget_history_id')->nullable();
                $table->decimal('original_budget_amount', 14, 2)->nullable();
                $table->string('budget_basis', 32)->default('latest_recorded_budget');
            });
        }
    }

    // Undo these database changes.
    public function down(): void
    {
        // Run this step if the input checks match.
        if (Schema::hasTable('ml_project_cost_snapshots') && Schema::hasColumn('ml_project_cost_snapshots', 'budget_history_id')) {
            // Update this part of the database structure.
            Schema::table('ml_project_cost_snapshots', fn (Blueprint $table) => $table->dropColumn(['budget_history_id', 'original_budget_amount', 'budget_basis']));
        }
        // Update this part of the database structure.
        Schema::dropIfExists('project_budget_history');
    }
};
