<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $genericAdmin = DB::table('fin_expense_category_tbl')->where('category_code', 'ADMINISTRATIVE_EXPENSES')->first();
            $duplicateOthers = DB::table('fin_expense_category_tbl')->where('category_code', 'SSS_PHILHEALTH_CONSTBOND')->first();

            $needsOtherAdmin = ($genericAdmin && DB::table('fin_expense_tbl')->where('fin_category_id', $genericAdmin->fin_category_id)->exists());
            if ($duplicateOthers) {
                $needsOtherAdmin = $needsOtherAdmin || DB::table('fin_expense_tbl')
                    ->where('fin_category_id', $duplicateOthers->fin_category_id)
                    ->whereRaw("LOWER(COALESCE(expense_description, '')) NOT LIKE '%contribution%'")
                    ->whereRaw("LOWER(COALESCE(expense_description, '')) NOT LIKE '%philhealth%'")
                    ->whereRaw("LOWER(COALESCE(expense_description, '')) NOT LIKE '%sss%'")
                    ->exists();
            }

            $otherAdminId = null;
            if ($needsOtherAdmin) {
                DB::table('fin_expense_category_tbl')->insertOrIgnore([
                    'category_code' => 'OTHER_ADMIN_COSTS', 'category_name' => 'Other Admin Costs',
                    'classification' => 'admin', 'is_active' => true,
                ]);
                $otherAdminId = DB::table('fin_expense_category_tbl')->where('category_code', 'OTHER_ADMIN_COSTS')->value('fin_category_id');
            }

            if ($genericAdmin) {
                if ($otherAdminId) {
                    DB::table('fin_expense_tbl')->where('fin_category_id', $genericAdmin->fin_category_id)
                        ->update(['fin_category_id' => $otherAdminId, 'updated_at' => now()]);
                }
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $genericAdmin->fin_category_id)->delete();
            }

            if ($duplicateOthers) {
                $contributions = DB::table('fin_expense_tbl')->where('fin_category_id', $duplicateOthers->fin_category_id)
                    ->where(function ($query): void {
                        $query->whereRaw("LOWER(COALESCE(expense_description, '')) LIKE '%contribution%'")
                            ->orWhereRaw("LOWER(COALESCE(expense_description, '')) LIKE '%philhealth%'")
                            ->orWhereRaw("LOWER(COALESCE(expense_description, '')) LIKE '%sss%'");
                    });
                if ((clone $contributions)->exists()) {
                    $employerCategory = DB::table('fin_expense_category_tbl')
                        ->whereIn('category_code', ['EMPLOYER_CONTRIBUTIONS', 'SSS_PHILHEALTH'])->first();
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
                if ($otherAdminId) {
                    DB::table('fin_expense_tbl')->where('fin_category_id', $duplicateOthers->fin_category_id)
                        ->update(['fin_category_id' => $otherAdminId, 'updated_at' => now()]);
                }
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $duplicateOthers->fin_category_id)->delete();
            }
        });
    }

    public function down(): void
    {
        // Expense category reassignment cannot be reversed without inventing the original assignments.
    }
};
