<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $categories = [
                ['CONSTRUCTION_SUPPLY', 'CONSTRUCTION_SUPPLY', 'Construction Supply', 'direct'],
                ['SALARIES_WAGES', 'SALARIES_WAGES', 'Salaries & Wages', 'direct'],
                ['PERMITS_TAXES', 'PERMIT_TAXES_LICENSES', 'Permit, Taxes & Licenses', 'direct'],
                ['EQUIPMENT_RENTAL', 'TRANSPORTATION_EXPENSES', 'Transportation Expenses', 'direct'],
                ['UTILITIES', 'UTILITIES_WATER_POWER', 'Utilities (Water & Power)', 'direct'],
                ['DELIVERY', 'DELIVERY_EXPENSE', 'Delivery Expense', 'direct'],
                ['OTHERS', 'OTHERS_SOS', 'Others (SOS, Etc.)', 'direct'],
                ['ADMIN_SALARIES', 'ADMIN_SALARIES_WAGES', 'Salaries & Wages', 'admin'],
                ['ADMIN_PERMIT_TAXES_LICENSES', 'ADMIN_PERMIT_TAXES_LICENSES', 'Permit, Taxes & Licenses', 'admin'],
                ['ADMIN_TRANSPORTATION_EXPENSES', 'ADMIN_TRANSPORTATION_EXPENSES', 'Transportation Expenses', 'admin'],
                ['ADMIN_UTILITIES_WATER_POWER', 'ADMIN_UTILITIES_WATER_POWER', 'Utilities (Water & Power)', 'admin'],
                ['ADMIN_DELIVERY_EXPENSE', 'ADMIN_DELIVERY_EXPENSE', 'Delivery Expense', 'admin'],
                ['OFFICE_RENT', 'ADMIN_RENT_EXPENSE', 'Rent Expense', 'admin'],
                ['ADMIN_STATIONARY_EXPENSE', 'ADMIN_STATIONARY_EXPENSE', 'Stationary Expense', 'admin'],
                ['ADMIN_DEPRECIATION_EXPENSE', 'ADMIN_DEPRECIATION_EXPENSE', 'Depreciation Expense', 'admin'],
                ['ADMIN_REPAIR_MAINTENANCE', 'ADMIN_REPAIR_MAINTENANCE', 'Repair & Maintenance', 'admin'],
                ['ADMIN_MISCELLANEOUS_EXPENSE', 'ADMIN_MISCELLANEOUS_EXPENSE', 'Miscellaneous Expense', 'admin'],
                ['ADMIN_PENALTY_EXPENSE', 'ADMIN_PENALTY_EXPENSE', 'Penalty Expense', 'admin'],
                ['EMPLOYER_CONTRIBUTIONS', 'ADMIN_SSS_PHILHEALTH', 'SSS, PhilHealth', 'admin'],
                ['OTHER_ADMIN_COSTS', 'ADMIN_OTHERS', 'Others', 'admin'],
            ];

            foreach ($categories as [$oldCode, $code, $name, $classification]) {
                $existing = DB::table('fin_expense_category_tbl')->where('category_code', $code)->first();
                $legacy = $oldCode === $code ? null : DB::table('fin_expense_category_tbl')->where('category_code', $oldCode)->first();
                if ($legacy && $classification === 'direct' && $legacy->classification !== 'direct') {
                    $legacy = null;
                }
                if ($existing && $legacy && $existing->fin_category_id !== $legacy->fin_category_id) {
                    DB::table('fin_expense_tbl')->where('fin_category_id', $legacy->fin_category_id)
                        ->update(['fin_category_id' => $existing->fin_category_id, 'updated_at' => now()]);
                    DB::table('fin_expense_category_tbl')->where('fin_category_id', $legacy->fin_category_id)->delete();
                } elseif (! $existing && $legacy) {
                    DB::table('fin_expense_category_tbl')->where('fin_category_id', $legacy->fin_category_id)
                        ->update(['category_code' => $code]);
                } elseif (! $existing) {
                    DB::table('fin_expense_category_tbl')->insert([
                        'category_code' => $code, 'category_name' => $name,
                        'classification' => $classification, 'is_active' => true,
                    ]);
                }
                DB::table('fin_expense_category_tbl')->where('category_code', $code)->update([
                    'category_name' => $name, 'classification' => $classification, 'is_active' => true,
                ]);
            }

            $legacyCodes = [
                'CONST_SUPPLY' => 'CONSTRUCTION_SUPPLY',
                'SSS_PHILHEALTH' => 'ADMIN_SSS_PHILHEALTH',
                'RENT' => 'ADMIN_RENT_EXPENSE',
                'STATIONERY' => 'ADMIN_STATIONARY_EXPENSE',
                'DEPRECIATION' => 'ADMIN_DEPRECIATION_EXPENSE',
                'REPAIR_MAINT' => 'ADMIN_REPAIR_MAINTENANCE',
                'MISC' => 'ADMIN_MISCELLANEOUS_EXPENSE',
                'PENALTY' => 'ADMIN_PENALTY_EXPENSE',
                'OTHERS' => 'ADMIN_OTHERS',
            ];
            foreach ($legacyCodes as $oldCode => $newCode) {
                $old = DB::table('fin_expense_category_tbl')->where('category_code', $oldCode)->first();
                $newId = DB::table('fin_expense_category_tbl')->where('category_code', $newCode)->value('fin_category_id');
                if (! $old || ! $newId || ($oldCode !== 'CONST_SUPPLY' && $old->classification === 'direct')) {
                    continue;
                }
                DB::table('fin_expense_tbl')->where('fin_category_id', $old->fin_category_id)
                    ->update(['fin_category_id' => $newId, 'updated_at' => now()]);
                DB::table('fin_expense_category_tbl')->where('fin_category_id', $old->fin_category_id)->delete();
            }
        });
    }

    public function down(): void
    {
        // Existing expenses may now use these categories; reversing them would lose meaning.
    }
};
