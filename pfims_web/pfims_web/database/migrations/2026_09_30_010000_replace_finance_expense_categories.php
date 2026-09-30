<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $requested = [
                ['CONSTRUCTION_SUPPLY', 'Construction Supply', 'direct', ['CONST_SUPPLY']],
                ['SALARIES_WAGES', 'Salaries & Wages', 'direct', []],
                ['PERMIT_TAXES_LICENSES', 'Permit, Taxes & Licenses', 'direct', []],
                ['TRANSPORTATION_EXPENSES', 'Transportation Expenses', 'direct', []],
                ['UTILITIES_WATER_POWER', 'Utilities (Water & Power)', 'direct', []],
                ['DELIVERY_EXPENSE', 'Delivery Expense', 'direct', []],
                ['OTHERS_SOS_ETC', 'Others (SOS, Etc.)', 'direct', ['OTHERS_SOS']],
                ['ADMIN_SALARIES_WAGES', 'Salaries & Wages', 'admin', ['ADMIN_SALARIES', 'OFFICE_SALARIES']],
                ['ADMIN_PERMIT_TAXES_LICENSES', 'Permit, Taxes & Licenses', 'admin', []],
                ['ADMIN_TRANSPORTATION_EXPENSES', 'Transportation Expenses', 'admin', []],
                ['ADMIN_UTILITIES_WATER_POWER', 'Utilities (Water & Power)', 'admin', []],
                ['ADMIN_DELIVERY_EXPENSE', 'Delivery Expense', 'admin', []],
                ['RENT_EXPENSE', 'Rent Expense', 'admin', ['ADMIN_RENT_EXPENSE', 'OFFICE_RENT', 'RENT']],
                ['STATIONARY_EXPENSE', 'Stationary Expense', 'admin', ['ADMIN_STATIONARY_EXPENSE', 'STATIONERY']],
                ['DEPRECIATION_EXPENSE', 'Depreciation Expense', 'admin', ['ADMIN_DEPRECIATION_EXPENSE', 'DEPRECIATION']],
                ['REPAIR_MAINTENANCE', 'Repair & Maintenance', 'admin', ['ADMIN_REPAIR_MAINTENANCE', 'REPAIR_MAINT']],
                ['MISCELLANEOUS_EXPENSE', 'Miscellaneous Expense', 'admin', ['ADMIN_MISCELLANEOUS_EXPENSE', 'OFFICE_EXPENSES', 'MISC']],
                ['PENALTY_EXPENSE', 'Penalty Expense', 'admin', ['ADMIN_PENALTY_EXPENSE', 'PENALTY']],
                ['SSS_PHILHEALTH', 'SSS, PhilHealth', 'admin', ['ADMIN_SSS_PHILHEALTH', 'EMPLOYER_CONTRIBUTIONS']],
                ['OTHERS', 'Others', 'admin', ['ADMIN_OTHERS', 'OTHER_ADMIN_COSTS']],
            ];

            foreach ($requested as [$code, $name, $type, $aliases]) {
                $category = DB::table('fin_expense_category_tbl')->where('category_code', $code)->first();
                if (! $category) {
                    $alias = DB::table('fin_expense_category_tbl')->whereIn('category_code', $aliases)->first();
                    if ($alias) {
                        DB::table('fin_expense_category_tbl')->where('fin_category_id', $alias->fin_category_id)->update(['category_code' => $code]);
                        $category = $alias;
                    } else {
                        $id = DB::table('fin_expense_category_tbl')->insertGetId([
                            'category_code' => $code, 'category_name' => $name,
                            'classification' => $type, 'is_active' => true,
                        ]);
                        $category = (object) ['fin_category_id' => $id];
                    }
                }
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $category->fin_category_id)->update([
                    'category_name' => $name, 'classification' => $type, 'is_active' => true,
                ]);
                foreach ($aliases as $aliasCode) {
                    $legacy = DB::table('fin_expense_category_tbl')->where('category_code', $aliasCode)->first();
                    if (! $legacy || $legacy->fin_category_id === $category->fin_category_id) continue;
                    DB::table('fin_expense_tbl')->where('fin_category_id', $legacy->fin_category_id)
                        ->update(['fin_category_id' => $category->fin_category_id]);
                    DB::table('fin_expense_category_tbl')->where('fin_category_id', $legacy->fin_category_id)->delete();
                }
            }

            $validCodes = array_column($requested, 0);
            $fallbackIds = [
                'direct' => DB::table('fin_expense_category_tbl')->where('category_code', 'OTHERS_SOS_ETC')->value('fin_category_id'),
                'admin' => DB::table('fin_expense_category_tbl')->where('category_code', 'MISCELLANEOUS_EXPENSE')->value('fin_category_id'),
            ];
            foreach (DB::table('fin_expense_category_tbl')->whereNotIn('category_code', $validCodes)->get() as $legacy) {
                $target = strtolower((string) $legacy->classification) === 'direct' ? $fallbackIds['direct'] : $fallbackIds['admin'];
                DB::table('fin_expense_tbl')->where('fin_category_id', $legacy->fin_category_id)->update(['fin_category_id' => $target]);
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $legacy->fin_category_id)->delete();
            }
        });
    }

    public function down(): void
    {
        // This migration preserves expenses while replacing legacy category identities.
    }
};
