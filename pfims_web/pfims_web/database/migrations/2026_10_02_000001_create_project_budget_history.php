<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_budget_history')) {
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
        DB::table('budgets_tbl')->orderBy('budget_id')->chunkById(200, function ($budgets) use ($recordedAt) {
            foreach ($budgets as $budget) {
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

        if (Schema::hasTable('ml_project_cost_snapshots') && ! Schema::hasColumn('ml_project_cost_snapshots', 'budget_history_id')) {
            Schema::table('ml_project_cost_snapshots', function (Blueprint $table) {
                $table->unsignedBigInteger('budget_history_id')->nullable();
                $table->decimal('original_budget_amount', 14, 2)->nullable();
                $table->string('budget_basis', 32)->default('latest_recorded_budget');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ml_project_cost_snapshots') && Schema::hasColumn('ml_project_cost_snapshots', 'budget_history_id')) {
            Schema::table('ml_project_cost_snapshots', fn (Blueprint $table) => $table->dropColumn(['budget_history_id', 'original_budget_amount', 'budget_basis']));
        }
        Schema::dropIfExists('project_budget_history');
    }
};
