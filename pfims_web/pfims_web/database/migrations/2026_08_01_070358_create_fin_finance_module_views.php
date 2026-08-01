<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reporting views that reconstruct every Excel sheet's "R TOTAL" /
     * "G TOTAL" on demand from fin_expense_tbl and friends, so totals
     * are computed at query time and can never drift out of sync the
     * way stored spreadsheet formulas can.
     */
    public function up(): void
    {
        // EXPOVRALL — project x month x category, ALL categories (direct+admin)
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_expovrall` AS
            SELECT
              COALESCE(p.project_name, 'OFFICE') AS project_name,
              fe.project_id,
              DATE_FORMAT(fe.expense_date, '%Y-%m-01') AS period_month,
              fc.category_code,
              fc.classification,
              SUM(fe.amount) AS category_total
            FROM fin_expense_tbl fe
            JOIN fin_expense_category_tbl fc ON fc.fin_category_id = fe.fin_category_id
            LEFT JOIN project_tbl p ON p.project_id = fe.project_id
            GROUP BY fe.project_id, period_month, fc.category_code, fc.classification
        ");

        // EXP DIRECT / DIRECT EXP — direct-only categories, per project per month
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_direct_expense` AS
            SELECT project_id, period_month, SUM(category_total) AS r_total
            FROM v_fin_expovrall
            WHERE classification = 'direct'
            GROUP BY project_id, period_month
        ");

        // ADMINEXP — admin-only categories, office-level and any admin costs tagged to a project
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_admin_expense` AS
            SELECT project_id, period_month, SUM(category_total) AS r_total
            FROM v_fin_expovrall
            WHERE classification = 'admin'
            GROUP BY project_id, period_month
        ");

        // OVERALLEXP / SUMMARYEXP — direct + admin combined, per project per month
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_overall_expense` AS
            SELECT project_id, period_month, SUM(category_total) AS g_total
            FROM v_fin_expovrall
            GROUP BY project_id, period_month
        ");

        // CONST BOND — G Total
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_construction_bond_total` AS
            SELECT project_id, SUM(amount) AS g_total
            FROM fin_construction_bond_tbl
            WHERE status = 'active'
            GROUP BY project_id
        ");

        // AR-AP — R Total per row + G Total per entry_type
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_receivable_payable` AS
            SELECT
              rp_id, entry_type, project_id, counterparty_name, entry_date, status,
              (amount_30d + amount_31_60d + amount_61_90d + amount_91_120d) AS r_total
            FROM fin_receivable_payable_tbl
        ");

        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_receivable_payable_grand_total` AS
            SELECT entry_type, SUM(r_total) AS g_total
            FROM v_fin_receivable_payable
            WHERE status = 'outstanding'
            GROUP BY entry_type
        ");

        // REPAIR — per asset per month total
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_repair_total` AS
            SELECT
              a.asset_name, a.asset_type,
              DATE_FORMAT(ee.expense_date, '%Y-%m-01') AS period_month,
              SUM(ee.amount) AS asset_total
            FROM fin_equipment_expense_tbl ee
            JOIN company_asset_tbl a ON a.asset_id = ee.asset_id
            WHERE a.asset_type IN ('vehicle','tool')
            GROUP BY a.asset_id, period_month
        ");

        // BACKHOE expense summary — Gas/Diesel + Payroll + Repair + Other + Delivery + Transpo per asset per month
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_backhoe_expense` AS
            SELECT
              a.asset_name,
              DATE_FORMAT(ee.expense_date, '%Y-%m-01') AS period_month,
              SUM(ee.amount) AS total_expense
            FROM fin_equipment_expense_tbl ee
            JOIN company_asset_tbl a ON a.asset_id = ee.asset_id
            WHERE a.asset_type = 'heavy_equipment'
            GROUP BY a.asset_id, period_month
        ");

        // BACKHOE rental income vs expense (profitability check)
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_backhoe_profitability` AS
            WITH months AS (
              SELECT asset_id, period_month FROM fin_equipment_rental_income_tbl
              GROUP BY asset_id, period_month
              UNION
              SELECT asset_id, DATE_FORMAT(expense_date, '%Y-%m-01') AS period_month
              FROM fin_equipment_expense_tbl
              GROUP BY asset_id, DATE_FORMAT(expense_date, '%Y-%m-01')
            )
            SELECT
              a.asset_id, a.asset_name, m.period_month,
              COALESCE(ri.total_income, 0)  AS rental_income,
              COALESCE(ex.total_expense, 0) AS total_expense,
              COALESCE(ri.total_income, 0) - COALESCE(ex.total_expense, 0) AS net_income
            FROM months m
            JOIN company_asset_tbl a ON a.asset_id = m.asset_id
            LEFT JOIN (
              SELECT asset_id, period_month, SUM(amount) AS total_income
              FROM fin_equipment_rental_income_tbl
              GROUP BY asset_id, period_month
            ) ri ON ri.asset_id = m.asset_id AND ri.period_month = m.period_month
            LEFT JOIN (
              SELECT asset_id, DATE_FORMAT(expense_date, '%Y-%m-01') AS period_month, SUM(amount) AS total_expense
              FROM fin_equipment_expense_tbl
              GROUP BY asset_id, DATE_FORMAT(expense_date, '%Y-%m-01')
            ) ex ON ex.asset_id = m.asset_id AND ex.period_month = m.period_month
            WHERE a.asset_type = 'heavy_equipment'
        ");

        // CASHASSET — total cash position per month, across all accounts
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_cash_position_summary` AS
            SELECT period_month, SUM(balance_amount) AS r_total
            FROM fin_cash_position_tbl
            GROUP BY period_month
        ");

        // PRFTDIRECT — profit/loss on a DIRECT-expense basis
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_profit_direct` AS
            SELECT
              p.project_id, p.project_name, p.start_date, p.actual_end_date,
              c.original_contract_price + c.additional_works_contract AS total_contract_price,
              c.original_payment_received + c.additional_works_payment AS total_payment,
              COALESCE(de.r_total, 0) AS project_expense,
              (c.original_contract_price + c.additional_works_contract)
                - (c.original_payment_received + c.additional_works_payment) AS accounts_receivable,
              (c.original_payment_received + c.additional_works_payment) - COALESCE(de.r_total, 0) AS profit_loss_payment_basis,
              (c.original_contract_price + c.additional_works_contract) - COALESCE(de.r_total, 0) AS profit_loss_contract_basis
            FROM fin_project_contract_tbl c
            JOIN project_tbl p ON p.project_id = c.project_id
            LEFT JOIN (
              SELECT project_id, SUM(r_total) AS r_total FROM v_fin_direct_expense GROUP BY project_id
            ) de ON de.project_id = p.project_id
        ");

        // PROFIT — profit/loss on an OVERALL basis (direct + allocated admin)
        DB::statement("
            CREATE OR REPLACE VIEW `v_fin_profit_overall` AS
            SELECT
              p.project_id, p.project_name, p.start_date, p.actual_end_date,
              c.original_contract_price + c.additional_works_contract AS total_contract_price,
              c.original_payment_received + c.additional_works_payment AS total_payment,
              COALESCE(oe.g_total, 0) AS project_expense,
              (c.original_contract_price + c.additional_works_contract)
                - (c.original_payment_received + c.additional_works_payment) AS accounts_receivable,
              (c.original_payment_received + c.additional_works_payment) - COALESCE(oe.g_total, 0) AS profit_loss_payment_basis,
              (c.original_contract_price + c.additional_works_contract) - COALESCE(oe.g_total, 0) AS profit_loss_contract_basis
            FROM fin_project_contract_tbl c
            JOIN project_tbl p ON p.project_id = c.project_id
            LEFT JOIN (
              SELECT project_id, SUM(g_total) AS g_total FROM v_fin_overall_expense GROUP BY project_id
            ) oe ON oe.project_id = p.project_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $views = [
            'v_fin_profit_overall',
            'v_fin_profit_direct',
            'v_fin_cash_position_summary',
            'v_fin_backhoe_profitability',
            'v_fin_backhoe_expense',
            'v_fin_repair_total',
            'v_fin_receivable_payable_grand_total',
            'v_fin_receivable_payable',
            'v_fin_construction_bond_total',
            'v_fin_overall_expense',
            'v_fin_admin_expense',
            'v_fin_direct_expense',
            'v_fin_expovrall',
        ];

        foreach ($views as $view) {
            DB::statement("DROP VIEW IF EXISTS `{$view}`");
        }
    }
};
