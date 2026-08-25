<?php

use App\Http\Controllers\Api\CompanyAssetController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinCashPositionController;
use App\Http\Controllers\Api\FinConstructionBondController;
use App\Http\Controllers\Api\FinEquipmentExpenseController;
use App\Http\Controllers\Api\FinEquipmentRentalIncomeController;
use App\Http\Controllers\Api\FinExpenseCategoryController;
use App\Http\Controllers\Api\FinExpenseController;
use App\Http\Controllers\Api\FinProjectContractController;
use App\Http\Controllers\Api\FinReceivablePayableController;
use App\Http\Controllers\Api\FinReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SupplierController;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['web', 'auth']);

Route::get('/sanctum/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json(['message' => 'Laravel API connected']);
});

// ─── AUTH ROUTES ─────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/profile', [AuthController::class, 'profile']);
Route::post('/profile/photo', [AuthController::class, 'uploadProfilePhoto']);
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::post('/profile/update', [AuthController::class, 'updateField']);
Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);

// Operational APIs are for the authenticated web application.
Route::middleware(['web', 'auth'])->group(function () {
    // ─── UNIT ROUTES ─────────────────────────────────────────────────
    Route::get('/units', function () {
        return response()->json(
            DB::table('unit_tbl')
                ->select('unit_id', 'unit_name')
                ->get()
        );
    });

    // ─── INVENTORY & SUPPLIER ROUTES ────────────────────────────────
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/lookup-data', [InventoryController::class, 'getLookupData']);
    Route::post('/inventory/item', [InventoryController::class, 'storeItem']);
    Route::patch('/inventory/item/{id}', [InventoryController::class, 'updateItem'])->whereNumber('id');
    Route::delete('/inventory/item/{id}', [InventoryController::class, 'destroyItem'])->whereNumber('id');
    Route::post('/inventory/transaction', [InventoryController::class, 'addTransaction']);
    Route::patch('/inventory/transaction/{id}', [InventoryController::class, 'updateTransaction'])->whereNumber('id');
    Route::delete('/inventory/transaction/{id}', [InventoryController::class, 'destroyTransaction'])->whereNumber('id');
    Route::get('/inventory/transactions', [InventoryController::class, 'getAllTransactions']);
    Route::get('/inventory/{itemId}/transactions', [InventoryController::class, 'getTransactions'])->whereNumber('itemId');

    Route::post('/inventory-transactions', [InventoryTransactionController::class, 'store']);
    Route::get('/inventory-transactions', [InventoryTransactionController::class, 'index']);
    Route::get('/inventory-categories', [InventoryController::class, 'categories']);
    Route::get('/inventory-items', [InventoryController::class, 'items']);
    Route::post('/inventory-items', [InventoryController::class, 'storeItem']);
    Route::put('/inventory-items/{id}', [InventoryController::class, 'updateItem'])->whereNumber('id');
    Route::delete('/inventory-items/{id}', [InventoryController::class, 'destroyItem'])->whereNumber('id');
    Route::get('/inventory-items-list', [InventoryController::class, 'itemList']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->whereNumber('id');
    Route::patch('/suppliers/{id}', [SupplierController::class, 'update'])->whereNumber('id');
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->whereNumber('id');

    // ─── PROJECT ROUTES ──────────────────────────────────────────────
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/list', [ProjectController::class, 'list']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->whereNumber('id');

    // ─── EXPENSE CATEGORIES (finance table, compatibility aliases) ──
    Route::get('/expense-categories', function () {
        return response()->json(
            DB::table('fin_expense_category_tbl')
                ->select(
                    'fin_category_id',
                    DB::raw('fin_category_id as expense_category_id'),
                    'category_code',
                    'category_name',
                    'classification'
                )
                ->where('is_active', true)
                ->orderBy('category_name')
                ->get()
        );
    });

    // ─── BUDGET ROUTES ──────────────────────────────────────────────
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets', [BudgetController::class, 'store']);
    Route::put('/budgets/{id}', [BudgetController::class, 'update']);
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

    // Legacy project expense endpoints used by the inventory screens.
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->whereNumber('id');
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->whereNumber('id');

    // ─── DASHBOARD ROUTES ───────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ─── NOTIFICATION ROUTES ────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::match(['post', 'put'], '/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::match(['post', 'put'], '/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']);

    Route::get('/test-notify', function () {
        app(NotificationService::class)->notify(
            title: 'Test',
            message: 'This is a test notification',
            type: 'test',
            kind: 'info',
        );

        return 'ok';
    });

    // =====================================================================
    // FINANCE MODULE ROUTES (Using fin_* tables - PRIMARY EXPENSE SYSTEM)
    // =====================================================================

    // 1. Finance Categories (for dropdowns in UI)
    Route::get('/finance-categories', [FinExpenseCategoryController::class, 'index']);

    // 2. Core Expense CRUD (Uses fin_expense_tbl - THIS IS THE MAIN EXPENSE TABLE)
    Route::get('/finance-expenses', [FinExpenseController::class, 'index']);
    Route::post('/finance-expenses', [FinExpenseController::class, 'store']);
    Route::put('/finance-expenses/{id}', [FinExpenseController::class, 'update']);
    Route::delete('/finance-expenses/{id}', [FinExpenseController::class, 'destroy']);

    // 3. Project Contracts (for PRFTDIRECT and PROFIT sheets)
    Route::get('/project-contracts', [FinProjectContractController::class, 'index']);
    Route::post('/project-contracts', [FinProjectContractController::class, 'store']);
    Route::put('/project-contracts/{id}', [FinProjectContractController::class, 'update']);
    Route::delete('/project-contracts/{id}', [FinProjectContractController::class, 'destroy']);

    // 4. Receivables/Payables
    Route::get('/receivables-payables', [FinReceivablePayableController::class, 'index']);
    Route::post('/receivables-payables', [FinReceivablePayableController::class, 'store']);
    Route::put('/receivables-payables/{id}', [FinReceivablePayableController::class, 'update']);
    Route::delete('/receivables-payables/{id}', [FinReceivablePayableController::class, 'destroy']);

    // 5. Construction Bonds
    Route::get('/construction-bonds', [FinConstructionBondController::class, 'index']);
    Route::post('/construction-bonds', [FinConstructionBondController::class, 'store']);
    Route::put('/construction-bonds/{id}', [FinConstructionBondController::class, 'update']);
    Route::delete('/construction-bonds/{id}', [FinConstructionBondController::class, 'destroy']);

    // 6. Cash Position
    Route::get('/cash-positions', [FinCashPositionController::class, 'index']);
    Route::post('/cash-positions', [FinCashPositionController::class, 'store']);
    Route::put('/cash-positions/{id}', [FinCashPositionController::class, 'update']);
    Route::delete('/cash-positions/{id}', [FinCashPositionController::class, 'destroy']);

    // 7. Equipment Expenses & Rental Income
    Route::get('/equipment-expenses', [FinEquipmentExpenseController::class, 'index']);
    Route::post('/equipment-expenses', [FinEquipmentExpenseController::class, 'store']);
    Route::put('/equipment-expenses/{id}', [FinEquipmentExpenseController::class, 'update']);
    Route::delete('/equipment-expenses/{id}', [FinEquipmentExpenseController::class, 'destroy']);

    Route::get('/equipment-rental-income', [FinEquipmentRentalIncomeController::class, 'index']);
    Route::post('/equipment-rental-income', [FinEquipmentRentalIncomeController::class, 'store']);
    Route::put('/equipment-rental-income/{id}', [FinEquipmentRentalIncomeController::class, 'update']);
    Route::delete('/equipment-rental-income/{id}', [FinEquipmentRentalIncomeController::class, 'destroy']);

    // 8. Reports (SQL Views)
    Route::get('/reports/expovrall', [FinReportController::class, 'getExpovrall']);
    Route::get('/reports/admin-expense', [FinReportController::class, 'getAdminExpense']);
    Route::get('/reports/profit-direct', [FinReportController::class, 'getProfitDirect']);
    Route::get('/reports/profit-overall', [FinReportController::class, 'getProfitOverall']);
    Route::get('/reports/cash-asset', [FinReportController::class, 'getCashAsset']);
    Route::get('/reports/backhoe-profitability', [FinReportController::class, 'getBackhoeProfitability']);
    Route::get('/reports/receivable-payable', [FinReportController::class, 'getReceivablePayable']);
    Route::get('/reports/construction-bond', [FinReportController::class, 'getConstructionBond']);
    Route::get('/reports/repair-total', [FinReportController::class, 'getRepairTotal']);
    Route::get('/reports/summary-expenses', [FinReportController::class, 'getSummaryExpenses']);

    // ─── COMPANY ASSETS ──────────────────────────────────────────────
    Route::get('/company-assets', [CompanyAssetController::class, 'index']);
    Route::get('/company-assets/type/{type}', [CompanyAssetController::class, 'getByType']);
    Route::get('/company-assets/{id}', [CompanyAssetController::class, 'show']);
    Route::post('/company-assets', [CompanyAssetController::class, 'store']);
    Route::put('/company-assets/{id}', [CompanyAssetController::class, 'update']);
    Route::delete('/company-assets/{id}', [CompanyAssetController::class, 'destroy']);
});
