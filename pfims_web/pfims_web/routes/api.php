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
use App\Http\Controllers\SystemSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Load requests for /user.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['web', 'auth']);

// Load requests for /sanctum/user.
Route::get('/sanctum/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Load requests for /test.
Route::get('/test', function () {
    return response()->json(['message' => 'Laravel API connected']);
});

// Auth routes
Route::post('/login', [AuthController::class, 'login'])->middleware(['web','throttle:6,1']);
// Submit requests for /forgot-password/send-otp.
Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp'])->middleware('throttle:6,1');
// Submit requests for /forgot-password/verify-otp.
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:6,1');
// Submit requests for /forgot-password/reset.
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset'])->middleware('throttle:6,1');

// Group routes that share these access rules or URL settings.
Route::middleware(['web', 'auth'])->group(function () {
    // Submit requests for /profile.
    Route::post('/profile', [AuthController::class, 'profile']);
    // Submit requests for /profile/photo.
    Route::post('/profile/photo', [AuthController::class, 'uploadProfilePhoto']);
    // Submit requests for /change-password.
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    // Submit requests for /profile/update.
    Route::post('/profile/update', [AuthController::class, 'updateField']);
});

// Operational APIs are for the authenticated web application.
Route::middleware(['web', 'auth', 'role.portal'])->group(function () {
    // Unit routes
    Route::get('/units', function () {
        return response()->json(
            DB::table('unit_tbl')
                ->select('unit_id', 'unit_name')
                ->get()
        );
    });

    // Load requests for /session/status.
    Route::get('/session/status', fn () => response()->json(['authenticated'=>true]));
    // Load requests for /trash.
    Route::get('/trash', [\App\Http\Controllers\TrashController::class, 'index']);
    // Submit requests for /trash/restore-all.
    Route::post('/trash/restore-all', [\App\Http\Controllers\TrashController::class, 'restoreAll']);
    // Delete requests for /trash.
    Route::delete('/trash', [\App\Http\Controllers\TrashController::class, 'empty']);
    // Submit requests for /trash/{id}/restore.
    Route::post('/trash/{id}/restore', [\App\Http\Controllers\TrashController::class, 'restore'])->whereNumber('id');
    // Load requests for /settings.
    Route::get('/settings', [SystemSettingsController::class, 'show']);
    // Update requests for /settings.
    Route::put('/settings', [SystemSettingsController::class, 'update']);

    // Inventory & supplier routes
    Route::get('/inventory', [InventoryController::class, 'index']);
    // Load requests for /inventory/lookup-data.
    Route::get('/inventory/lookup-data', [InventoryController::class, 'getLookupData']);
    // Submit requests for /inventory/item.
    Route::post('/inventory/item', [InventoryController::class, 'storeItem']);
    // Update requests for /inventory/item/{id}.
    Route::patch('/inventory/item/{id}', [InventoryController::class, 'updateItem'])->whereNumber('id');
    // Delete requests for /inventory/item/{id}.
    Route::delete('/inventory/item/{id}', [InventoryController::class, 'destroyItem'])->whereNumber('id');
    // Submit requests for /inventory/transaction.
    Route::post('/inventory/transaction', [InventoryController::class, 'addTransaction']);
    // Update requests for /inventory/transaction/{id}.
    Route::patch('/inventory/transaction/{id}', [InventoryController::class, 'updateTransaction'])->whereNumber('id');
    // Delete requests for /inventory/transaction/{id}.
    Route::delete('/inventory/transaction/{id}', [InventoryController::class, 'destroyTransaction'])->whereNumber('id');
    // Load requests for /inventory/transactions.
    Route::get('/inventory/transactions', [InventoryController::class, 'getAllTransactions']);
    // Load requests for /inventory/{itemId}/transactions.
    Route::get('/inventory/{itemId}/transactions', [InventoryController::class, 'getTransactions'])->whereNumber('itemId');

    // Submit requests for /inventory-transactions.
    Route::post('/inventory-transactions', [InventoryController::class, 'addTransaction']);
    // Load requests for /inventory-transactions.
    Route::get('/inventory-transactions', [InventoryTransactionController::class, 'index']);
    // Load requests for /inventory-categories.
    Route::get('/inventory-categories', [InventoryController::class, 'categories']);
    // Load requests for /inventory-items.
    Route::get('/inventory-items', [InventoryController::class, 'items']);
    // Submit requests for /inventory-items.
    Route::post('/inventory-items', [InventoryController::class, 'storeItem']);
    // Update requests for /inventory-items/{id}.
    Route::put('/inventory-items/{id}', [InventoryController::class, 'updateItem'])->whereNumber('id');
    // Delete requests for /inventory-items/{id}.
    Route::delete('/inventory-items/{id}', [InventoryController::class, 'destroyItem'])->whereNumber('id');
    // Load requests for /inventory-items-list.
    Route::get('/inventory-items-list', [InventoryController::class, 'itemList']);

    // Load requests for /suppliers.
    Route::get('/suppliers', [SupplierController::class, 'index']);
    // Submit requests for /suppliers.
    Route::post('/suppliers', [SupplierController::class, 'store']);
    // Load requests for /suppliers/{id}.
    Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->whereNumber('id');
    // Update requests for /suppliers/{id}.
    Route::patch('/suppliers/{id}', [SupplierController::class, 'update'])->whereNumber('id');
    // Delete requests for /suppliers/{id}.
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->whereNumber('id');

    // Project routes
    Route::get('/projects', [ProjectController::class, 'index']);
    // Load requests for /projects/list.
    Route::get('/projects/list', [ProjectController::class, 'list']);
    // Load requests for /project-managers.
    Route::get('/project-managers', [ProjectController::class, 'managers']);
    // Load requests for /project-phases.
    Route::get('/project-phases', [ProjectController::class, 'phases']);
    // Submit requests for /projects.
    Route::post('/projects', [ProjectController::class, 'store']);
    // Update requests for /projects/{id}.
    Route::put('/projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
    // Delete requests for /projects/{id}.
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->whereNumber('id');

    // Expense categories (finance table, compatibility aliases)
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

    // Budget routes
    Route::get('/budgets', [BudgetController::class, 'index']);
    // Submit requests for /budgets.
    Route::post('/budgets', [BudgetController::class, 'store']);
    // Load requests for /budgets/{id}/history.
    Route::get('/budgets/{id}/history', [BudgetController::class, 'history'])->whereNumber('id');
    // Update requests for /budgets/{id}.
    Route::put('/budgets/{id}', [BudgetController::class, 'update']);
    // Delete requests for /budgets/{id}.
    Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

    // Legacy project expense endpoints used by the inventory screens.
    Route::get('/expenses', [ExpenseController::class, 'index']);
    // Submit requests for /expenses.
    Route::post('/expenses', [ExpenseController::class, 'store']);
    // Update requests for /expenses/{id}.
    Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->whereNumber('id');
    // Delete requests for /expenses/{id}.
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->whereNumber('id');

    // Dashboard routes
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    // Load requests for /notifications/unread-count.
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::match(['post', 'put'], '/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::match(['post', 'put'], '/notifications/{id}/read', [NotificationController::class, 'markRead']);
    // Delete requests for /notifications/{id}.
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    // Delete requests for /notifications.
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']);

    // =====================================================================
    // FINANCE MODULE ROUTES (Using fin_* tables - PRIMARY EXPENSE SYSTEM)
    // =====================================================================

    // 1. Finance Categories (for dropdowns in UI)
    Route::get('/finance-categories', [FinExpenseCategoryController::class, 'index']);

    // 2. Core Expense CRUD (Uses fin_expense_tbl - THIS IS THE MAIN EXPENSE TABLE)
    Route::get('/finance-expenses', [FinExpenseController::class, 'index']);
    // Submit requests for /finance-expenses.
    Route::post('/finance-expenses', [FinExpenseController::class, 'store']);
    // Update requests for /finance-expenses/{id}.
    Route::put('/finance-expenses/{id}', [FinExpenseController::class, 'update']);
    // Delete requests for /finance-expenses/{id}.
    Route::delete('/finance-expenses/{id}', [FinExpenseController::class, 'destroy']);

    // 3. Project Contracts (for PRFTDIRECT and PROFIT sheets)
    Route::get('/project-contracts', [FinProjectContractController::class, 'index']);
    // Submit requests for /project-contracts.
    Route::post('/project-contracts', [FinProjectContractController::class, 'store']);
    // Update requests for /project-contracts/{id}.
    Route::put('/project-contracts/{id}', [FinProjectContractController::class, 'update']);
    // Delete requests for /project-contracts/{id}.
    Route::delete('/project-contracts/{id}', [FinProjectContractController::class, 'destroy']);

    // 4. Receivables/Payables
    Route::get('/receivables-payables', [FinReceivablePayableController::class, 'index']);
    // Submit requests for /receivables-payables.
    Route::post('/receivables-payables', [FinReceivablePayableController::class, 'store']);
    // Update requests for /receivables-payables/{id}.
    Route::put('/receivables-payables/{id}', [FinReceivablePayableController::class, 'update']);
    // Delete requests for /receivables-payables/{id}.
    Route::delete('/receivables-payables/{id}', [FinReceivablePayableController::class, 'destroy']);

    // 5. Construction Bonds
    Route::get('/construction-bonds', [FinConstructionBondController::class, 'index']);
    // Submit requests for /construction-bonds.
    Route::post('/construction-bonds', [FinConstructionBondController::class, 'store']);
    // Update requests for /construction-bonds/{id}.
    Route::put('/construction-bonds/{id}', [FinConstructionBondController::class, 'update']);
    // Delete requests for /construction-bonds/{id}.
    Route::delete('/construction-bonds/{id}', [FinConstructionBondController::class, 'destroy']);

    // 6. Cash Asset
    Route::get('/cash-accounts', [FinCashPositionController::class, 'accounts']);
    // Submit requests for /cash-accounts.
    Route::post('/cash-accounts', [FinCashPositionController::class, 'storeAccount']);
    // Update requests for /cash-accounts/{id}.
    Route::put('/cash-accounts/{id}', [FinCashPositionController::class, 'updateAccount'])->whereNumber('id');
    // Delete requests for /cash-accounts/{id}.
    Route::delete('/cash-accounts/{id}', [FinCashPositionController::class, 'destroyAccount'])->whereNumber('id');
    // Load requests for /cash-positions.
    Route::get('/cash-positions', [FinCashPositionController::class, 'index']);
    // Submit requests for /cash-positions.
    Route::post('/cash-positions', [FinCashPositionController::class, 'store']);
    // Update requests for /cash-positions/{id}.
    Route::put('/cash-positions/{id}', [FinCashPositionController::class, 'update']);
    // Delete requests for /cash-positions/{id}.
    Route::delete('/cash-positions/{id}', [FinCashPositionController::class, 'destroy']);

    // Update requests for /equipment-period.
    Route::put('/equipment-period', [\App\Http\Controllers\Api\EquipmentPeriodController::class, 'update']);
    // Delete requests for /equipment-period.
    Route::delete('/equipment-period', [\App\Http\Controllers\Api\EquipmentPeriodController::class, 'destroy']);

    // 7. Equipment Expenses & Rental Income
    Route::get('/equipment-expenses', [FinEquipmentExpenseController::class, 'index']);
    // Submit requests for /equipment-expenses.
    Route::post('/equipment-expenses', [FinEquipmentExpenseController::class, 'store']);
    // Update requests for /equipment-expenses/{id}.
    Route::put('/equipment-expenses/{id}', [FinEquipmentExpenseController::class, 'update']);
    // Delete requests for /equipment-expenses/{id}.
    Route::delete('/equipment-expenses/{id}', [FinEquipmentExpenseController::class, 'destroy']);

    // Load requests for /equipment-rental-income.
    Route::get('/equipment-rental-income', [FinEquipmentRentalIncomeController::class, 'index']);
    // Submit requests for /equipment-rental-income.
    Route::post('/equipment-rental-income', [FinEquipmentRentalIncomeController::class, 'store']);
    // Update requests for /equipment-rental-income/{id}.
    Route::put('/equipment-rental-income/{id}', [FinEquipmentRentalIncomeController::class, 'update']);
    // Delete requests for /equipment-rental-income/{id}.
    Route::delete('/equipment-rental-income/{id}', [FinEquipmentRentalIncomeController::class, 'destroy']);

    // 8. Reports (SQL Views)
    Route::get('/reports/expovrall', [FinReportController::class, 'getExpovrall']);
    // Load requests for /reports/admin-expense.
    Route::get('/reports/admin-expense', [FinReportController::class, 'getAdminExpense']);
    // Load requests for /reports/profit-direct.
    Route::get('/reports/profit-direct', [FinReportController::class, 'getProfitDirect']);
    // Load requests for /reports/profit-overall.
    Route::get('/reports/profit-overall', [FinReportController::class, 'getProfitOverall']);
    // Load requests for /reports/cash-asset.
    Route::get('/reports/cash-asset', [FinReportController::class, 'getCashAsset']);
    // Load requests for /reports/backhoe-profitability.
    Route::get('/reports/backhoe-profitability', [FinReportController::class, 'getBackhoeProfitability']);
    // Load requests for /reports/receivable-payable.
    Route::get('/reports/receivable-payable', [FinReportController::class, 'getReceivablePayable']);
    // Load requests for /reports/construction-bond.
    Route::get('/reports/construction-bond', [FinReportController::class, 'getConstructionBond']);
    // Load requests for /reports/repair-total.
    Route::get('/reports/repair-total', [FinReportController::class, 'getRepairTotal']);
    // Load requests for /reports/summary-expenses.
    Route::get('/reports/summary-expenses', [FinReportController::class, 'getSummaryExpenses']);

    // Company assets
    Route::get('/company-assets', [CompanyAssetController::class, 'index']);
    // Load requests for /company-assets/type/{type}.
    Route::get('/company-assets/type/{type}', [CompanyAssetController::class, 'getByType']);
    // Load requests for /company-assets/{id}.
    Route::get('/company-assets/{id}', [CompanyAssetController::class, 'show']);
    // Submit requests for /company-assets.
    Route::post('/company-assets', [CompanyAssetController::class, 'store']);
    // Update requests for /company-assets/{id}.
    Route::put('/company-assets/{id}', [CompanyAssetController::class, 'update']);
    // Delete requests for /company-assets/{id}.
    Route::delete('/company-assets/{id}', [CompanyAssetController::class, 'destroy']);
});
