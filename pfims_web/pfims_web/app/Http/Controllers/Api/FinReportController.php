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

    public function getProfitDirect()
    {
        $profits = DB::table('v_fin_profit_direct')->get();
        return response()->json($profits);
    }

    public function getProfitOverall()
    {
        $profits = DB::table('v_fin_profit_overall')->get();
        return response()->json($profits);
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
        $query = DB::table('v_fin_backhoe_profitability');

        if ($assetId) {
            $query->where('asset_id', $assetId);
        }

        return response()->json($query->get());
    }

    public function getReceivablePayable(Request $request)
    {
        $entryType = $request->input('entry_type');
        $query = DB::table('v_fin_receivable_payable');

        if ($entryType) {
            $query->where('entry_type', $entryType);
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