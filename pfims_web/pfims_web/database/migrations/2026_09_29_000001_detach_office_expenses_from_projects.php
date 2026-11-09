<?php

use App\Services\ProjectCostLedger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Run these database steps together. Undo changes if a step fails.
        DB::transaction(function (): void {
            $adminExpenses = DB::table('fin_expense_tbl as expense')
                ->join('fin_expense_category_tbl as category', 'category.fin_category_id', '=', 'expense.fin_category_id')
                ->whereRaw('LOWER(category.classification) = ?', ['admin'])
                ->whereNotNull('expense.project_id');

            $affectedProjects = (clone $adminExpenses)->distinct()->pluck('expense.project_id');
            // Apply this action to each record.
            (clone $adminExpenses)->pluck('expense.fin_expense_id')->chunk(500)->each(function ($expenseIds): void {
                DB::table('fin_expense_tbl')->whereIn('fin_expense_id', $expenseIds)
                    ->update(['project_id' => null, 'updated_at' => now()]);
            });

            // Process each project id.
            foreach ($affectedProjects as $projectId) {
                app(ProjectCostLedger::class)->syncBudget((int) $projectId);
            }
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // The former project links cannot be rebuilt without inventing data.
    }
};
