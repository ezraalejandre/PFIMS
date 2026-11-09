<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Run these database steps together. Undo changes if a step fails.
        DB::transaction(function (): void {
            $genericAdmin = DB::table('fin_expense_category_tbl')->where('category_code', 'ADMINISTRATIVE_EXPENSES')->first();
            $duplicateOthers = DB::table('fin_expense_category_tbl')->where('category_code', 'SSS_PHILHEALTH_CONSTBOND')->first();

            $needsOtherAdmin = ($genericAdmin && DB::table('fin_expense_tbl')->where('fin_category_id', $genericAdmin->fin_category_id)->exists());
            // Run this step if the duplicate others is set.
            if ($duplicateOthers) {
                $needsOtherAdmin = $needsOtherAdmin || DB::table('fin_expense_tbl')
                    ->where('fin_category_id', $duplicateOthers->fin_category_id)
                    ->whereRaw("LOWER(COALESCE(expense_description, '')) NOT LIKE '%contribution%'")
                    ->whereRaw("LOWER(COALESCE(expense_description, '')) NOT LIKE '%philhealth%'")
                    ->whereRaw("LOWER(COALESCE(expense_description, '')) NOT LIKE '%sss%'")
                    ->exists();
            }

            $otherAdminId = null;
            // Run this step if the needs other admin is set.
            if ($needsOtherAdmin) {
                DB::table('fin_expense_category_tbl')->insertOrIgnore([
                    'category_code' => 'OTHER_ADMIN_COSTS', 'category_name' => 'Other Admin Costs',
                    'classification' => 'admin', 'is_active' => true,
                ]);
                $otherAdminId = DB::table('fin_expense_category_tbl')->where('category_code', 'OTHER_ADMIN_COSTS')->value('fin_category_id');
            }

            // Run this step if the generic admin is set.
            if ($genericAdmin) {
                // Run this step if the other admin id is set.
                if ($otherAdminId) {
                    DB::table('fin_expense_tbl')->where('fin_category_id', $genericAdmin->fin_category_id)
                        ->update(['fin_category_id' => $otherAdminId, 'updated_at' => now()]);
                }
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $genericAdmin->fin_category_id)->delete();
            }

            // Run this step if the duplicate others is set.
            if ($duplicateOthers) {
                $contributions = DB::table('fin_expense_tbl')->where('fin_category_id', $duplicateOthers->fin_category_id)
                    // Group these checks when finding matching records.
                    ->where(function ($query): void {
                        $query->whereRaw("LOWER(COALESCE(expense_description, '')) LIKE '%contribution%'")
                            ->orWhereRaw("LOWER(COALESCE(expense_description, '')) LIKE '%philhealth%'")
                            ->orWhereRaw("LOWER(COALESCE(expense_description, '')) LIKE '%sss%'");
                    });
                // Run this step if the exists check matches.
                if ((clone $contributions)->exists()) {
                    $employerCategory = DB::table('fin_expense_category_tbl')
                        ->whereIn('category_code', ['EMPLOYER_CONTRIBUTIONS', 'SSS_PHILHEALTH'])->first();
                    // Run this step if the employer category is missing or false.
                    if (! $employerCategory) {
                        DB::table('fin_expense_category_tbl')->insertOrIgnore([
                            'category_code' => 'EMPLOYER_CONTRIBUTIONS', 'category_name' => 'Employer Contributions',
                            'classification' => 'admin', 'is_active' => true,
                        ]);
                        $employerCategory = DB::table('fin_expense_category_tbl')
                            ->where('category_code', 'EMPLOYER_CONTRIBUTIONS')->first();
                    }
                    (clone $contributions)->update(['fin_category_id' => $employerCategory->fin_category_id, 'updated_at' => now()]);
                }
                // Run this step if the other admin id is set.
                if ($otherAdminId) {
                    DB::table('fin_expense_tbl')->where('fin_category_id', $duplicateOthers->fin_category_id)
                        ->update(['fin_category_id' => $otherAdminId, 'updated_at' => now()]);
                }
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $duplicateOthers->fin_category_id)->delete();
            }
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Expense category reassignment cannot be reversed without inventing the original assignments.
    }
};
