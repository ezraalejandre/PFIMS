<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class FinReportController extends Controller
{
    public function getExpovrall(Request $request)
    {
        $period = $request->input('period', date('Y-m-01'));
        $projectId = $request->input('project_id');

        $query = DB::table('v_fin_expovrall')
            ->where('period_month', $period);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return response()->json($query->get());
    }

    public function getAdminExpense(Request $request)
    {
        $period = $request->input('period', date('Y-m-01'));
        $projectId = $request->input('project_id');

        $query = DB::table('v_fin_admin_expense')
            ->where('period_month', $period);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return response()->json($query->get());
    }

    /**
     * Get Profit/Loss data (Direct Expenses basis)
     * Uses budget from budgets_tbl as contract price
     * Shows ONLY projects that have a contract record
     */
    public function getProfitDirect()
    {
        try {
            $results = DB::table('fin_project_contract_tbl as c')
                ->join('project_tbl as p', 'p.project_id', '=', 'c.project_id')
                ->leftJoin('budgets_tbl as b', 'p.project_id', '=', 'b.project_id')
                ->leftJoin(DB::raw('(
                    SELECT 
                        fe.project_id,
                        SUM(fe.amount) as total_expense
                    FROM fin_expense_tbl fe
                    GROUP BY fe.project_id
                ) as all_exp'), 'all_exp.project_id', '=', 'p.project_id')
                ->select(
                    'p.project_id',
                    'p.project_name',
                    'p.start_date',
                    'p.actual_end_date',
                    'c.contract_id',
                    DB::raw('COALESCE(b.budget_amount, c.original_contract_price, 0) as original_contract_price'),
                    DB::raw('COALESCE(c.additional_works_contract, 0) as additional_works_contract'),
                    DB::raw('COALESCE(c.original_payment_received, 0) as original_payment_received'),
                    DB::raw('COALESCE(c.additional_works_payment, 0) as additional_works_payment'),
                    DB::raw('COALESCE(all_exp.total_expense, 0) as project_expense'),
                    DB::raw('COALESCE(c.remarks, "") as remarks'),
                    // Calculated fields
                    DB::raw('(COALESCE(b.budget_amount, c.original_contract_price, 0) + COALESCE(c.additional_works_contract, 0)) as total_contract_price'),
                    DB::raw('(COALESCE(c.original_payment_received, 0) + COALESCE(c.additional_works_payment, 0)) as total_payment'),
                    DB::raw('(COALESCE(b.budget_amount, c.original_contract_price, 0) + COALESCE(c.additional_works_contract, 0)) - (COALESCE(c.original_payment_received, 0) + COALESCE(c.additional_works_payment, 0)) as accounts_receivable'),
                    DB::raw('(COALESCE(c.original_payment_received, 0) + COALESCE(c.additional_works_payment, 0)) - COALESCE(all_exp.total_expense, 0) as profit_loss_payment_basis'),
                    DB::raw('(COALESCE(b.budget_amount, c.original_contract_price, 0) + COALESCE(c.additional_works_contract, 0)) - COALESCE(all_exp.total_expense, 0) as profit_loss_contract_basis')
                )
                ->orderBy('p.project_name')
                ->get();

            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Profit/Loss data (Overall Expenses basis)
     * Uses budget from budgets_tbl as contract price
     * Shows ONLY projects that have a contract record
     */
    public function getProfitOverall()
    {
        try {
            $results = DB::table('fin_project_contract_tbl as c')
                ->join('project_tbl as p', 'p.project_id', '=', 'c.project_id')
                ->leftJoin('budgets_tbl as b', 'p.project_id', '=', 'b.project_id')
                ->leftJoin(DB::raw('(
                    SELECT 
                        fe.project_id,
                        SUM(fe.amount) as total_expense
                    FROM fin_expense_tbl fe
                    GROUP BY fe.project_id
                ) as all_exp'), 'all_exp.project_id', '=', 'p.project_id')
                ->select(
                    'p.project_id',
                    'p.project_name',
                    'p.start_date',
                    'p.actual_end_date',
                    'c.contract_id',
                    DB::raw('COALESCE(b.budget_amount, c.original_contract_price, 0) as original_contract_price'),
                    DB::raw('COALESCE(c.additional_works_contract, 0) as additional_works_contract'),
                    DB::raw('COALESCE(c.original_payment_received, 0) as original_payment_received'),
                    DB::raw('COALESCE(c.additional_works_payment, 0) as additional_works_payment'),
                    DB::raw('COALESCE(all_exp.total_expense, 0) as project_expense'),
                    DB::raw('COALESCE(c.remarks, "") as remarks'),
                    // Calculated fields
                    DB::raw('(COALESCE(b.budget_amount, c.original_contract_price, 0) + COALESCE(c.additional_works_contract, 0)) as total_contract_price'),
                    DB::raw('(COALESCE(c.original_payment_received, 0) + COALESCE(c.additional_works_payment, 0)) as total_payment'),
                    DB::raw('(COALESCE(b.budget_amount, c.original_contract_price, 0) + COALESCE(c.additional_works_contract, 0)) - (COALESCE(c.original_payment_received, 0) + COALESCE(c.additional_works_payment, 0)) as accounts_receivable'),
                    DB::raw('(COALESCE(c.original_payment_received, 0) + COALESCE(c.additional_works_payment, 0)) - COALESCE(all_exp.total_expense, 0) as profit_loss_payment_basis'),
                    DB::raw('(COALESCE(b.budget_amount, c.original_contract_price, 0) + COALESCE(c.additional_works_contract, 0)) - COALESCE(all_exp.total_expense, 0) as profit_loss_contract_basis')
                )
                ->orderBy('p.project_name')
                ->get();

            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getCashAsset(Request $request)
    {
        $period = $request->input('period', date('Y-m-01'));
        $accountId = $request->input('account_id');

        $query = DB::table('fin_cash_position_tbl')
            ->leftJoin('company_bank_account_tbl', 'fin_cash_position_tbl.account_id', '=', 'company_bank_account_tbl.account_id')
            ->select('fin_cash_position_tbl.*', 'company_bank_account_tbl.account_name', 'company_bank_account_tbl.account_type');

        if ($period) {
            $query->where('fin_cash_position_tbl.period_month', $period);
        }

        if ($accountId) {
            $query->where('fin_cash_position_tbl.account_id', $accountId);
        }

        return response()->json($query->get());
    }

    public function getBackhoeProfitability(Request $request)
    {
        $assetId = $request->input('asset_id');
        $period = $request->input('period');
        
        $query = DB::table('fin_equipment_expense_tbl as ee')
            ->join('company_asset_tbl as a', 'a.asset_id', '=', 'ee.asset_id')
            ->leftJoin(DB::raw('(
                SELECT 
                    asset_id,
                    period_month,
                    SUM(amount) as rental_income
                FROM fin_equipment_rental_income_tbl
                GROUP BY asset_id, period_month
            ) as ri'), function($join) {
                $join->on('ri.asset_id', '=', 'ee.asset_id')
                     ->on('ri.period_month', '=', DB::raw("DATE_FORMAT(ee.expense_date, '%Y-%m-01')"));
            })
            ->where('a.asset_type', 'heavy_equipment');

        if ($assetId) {
            $query->where('ee.asset_id', $assetId);
        }

        if ($period) {
            $query->where('ee.expense_date', '>=', $period)
                  ->where('ee.expense_date', '<', DB::raw("DATE_ADD('$period', INTERVAL 1 MONTH)"));
        }

        $results = $query->select(
            'a.asset_id',
            'a.asset_name',
            'ee.expense_type',
            'ee.amount',
            DB::raw("DATE_FORMAT(ee.expense_date, '%Y-%m-01') as period_month"),
            DB::raw('COALESCE(ri.rental_income, 0) as rental_income'),
            DB::raw('COALESCE(ri.rental_income, 0) - SUM(ee.amount) OVER (PARTITION BY a.asset_id, DATE_FORMAT(ee.expense_date, "%Y-%m-01")) as net_income')
        )
        ->orderBy('a.asset_name')
        ->orderBy('period_month')
        ->get();

        // Recalculate net income properly
        $results = $results->map(function($item) {
            // Find total expense for this asset and period
            $totalExpense = DB::table('fin_equipment_expense_tbl')
                ->where('asset_id', $item->asset_id)
                ->where(DB::raw("DATE_FORMAT(expense_date, '%Y-%m-01')"), $item->period_month)
                ->sum('amount');
            
            $item->total_expense = $totalExpense;
            $item->net_income = ($item->rental_income ?? 0) - $totalExpense;
            return $item;
        });

        return response()->json($results);
    }

    /**
     * Get Receivable/Payable report with proper column mapping
     */
    public function getReceivablePayable(Request $request)
    {
        $entryType = $request->input('entry_type');
        
        $query = DB::table('fin_receivable_payable_tbl as rp')
            ->leftJoin('project_tbl as p', 'p.project_id', '=', 'rp.project_id')
            ->select(
                'rp.rp_id',
                'rp.entry_type',
                'rp.project_id',
                'rp.counterparty_name',
                'rp.entry_date',
                'rp.status',
                'rp.remarks',
                'p.project_name',
                'rp.amount_30d',
                'rp.amount_31_60d',
                'rp.amount_61_90d',
                'rp.amount_91_120d',
                DB::raw('(rp.amount_30d + rp.amount_31_60d + rp.amount_61_90d + rp.amount_91_120d) as r_total')
            )
            ->orderBy('rp.entry_date', 'desc');

        if ($entryType) {
            $query->where('rp.entry_type', $entryType);
        }

        return response()->json($query->get());
    }

    public function getConstructionBond(Request $request)
    {
        $projectId = $request->input('project_id');
        $query = DB::table('fin_construction_bond_tbl')
            ->leftJoin('project_tbl', 'fin_construction_bond_tbl.project_id', '=', 'project_tbl.project_id')
            ->select('fin_construction_bond_tbl.*', 'project_tbl.project_name');

        if ($projectId) {
            $query->where('fin_construction_bond_tbl.project_id', $projectId);
        }

        return response()->json($query->get());
    }

    public function getRepairTotal(Request $request)
    {
        $period = $request->input('period', date('Y-m-01'));
        $query = DB::table('v_fin_repair_total')
            ->where('period_month', $period);

        return response()->json($query->get());
    }

    public function getSummaryExpenses(Request $request)
    {
        $period = $request->input('period', date('Y-m-01'));
        $query = DB::table('v_fin_overall_expense')
            ->where('period_month', $period);

        return response()->json($query->get());
    }
}