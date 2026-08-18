<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Services\NotificationService;
use App\Http\Controllers\Api\FinExpenseCategoryController;
use App\Http\Controllers\Api\FinExpenseController;
use App\Http\Controllers\Api\FinReportController;
use App\Http\Controllers\Api\FinProjectContractController;
use App\Http\Controllers\Api\FinReceivablePayableController;
use App\Http\Controllers\Api\FinConstructionBondController;
use App\Http\Controllers\Api\FinCashPositionController;
use App\Http\Controllers\Api\FinEquipmentExpenseController;
use App\Http\Controllers\Api\FinEquipmentRentalIncomeController;
use App\Http\Controllers\Api\CompanyAssetController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth');

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

// ─── UNIT ROUTES ─────────────────────────────────────────────────
Route::get('/units', function () {
    return response()->json(
        DB::table('unit_tbl')
            ->select('unit_id', 'unit_name')
            ->get()
    );
});

// ─── PROJECT ROUTES ──────────────────────────────────────────────
Route::get('/projects', function () {
    return response()->json(
        DB::table('project_tbl as p')
            ->leftJoin('budgets_tbl as b', 'p.project_id', '=', 'b.project_id')
            ->select(
                'p.project_id',
                'p.project_name',
                'p.client_name',
                DB::raw('COALESCE(b.budget_amount, 0) as budget'),
                'p.project_manager',
                'p.start_date',
                'p.estimated_end_date',
                'p.actual_end_date',
                'p.worker_count',
                'p.phase',
                'p.completion_percentage',
                'p.status'
            )
            ->get()
    );
});

Route::get('/projects/list', function () {
    return response()->json(
        DB::table('project_tbl')
            ->select(
                'project_id',
                'project_name',
                'client_name',
                'project_manager',
                'start_date',
                'estimated_end_date',
                'actual_end_date',
                'worker_count',
                'phase',
                'completion_percentage',
                'status'
            )
            ->orderByDesc('project_id')
            ->get()
    );
});

Route::post('/projects', function (Request $request) {
    $validated = $request->validate([
        'project_name'       => ['required', 'string', 'max:100'],
        'client_name'        => ['required', 'string', 'max:100'],
        'project_manager'    => ['required', 'string', 'max:100'],
        'start_date'         => ['required', 'date'],
        'estimated_end_date' => ['required', 'date', 'after_or_equal:start_date'],
        'worker_count'       => ['nullable', 'integer', 'min:0'],
        'budget'             => ['nullable', 'numeric', 'min:0'],
    ]);

    $projectId = DB::table('project_tbl')->insertGetId([
        'project_name'          => $validated['project_name'],
        'client_name'           => $validated['client_name'],
        'project_manager'       => $validated['project_manager'],
        'start_date'            => $validated['start_date'],
        'estimated_end_date'    => $validated['estimated_end_date'],
        'worker_count'          => $validated['worker_count'] ?? 0,
        'phase'                 => 'Planning',
        'completion_percentage' => 0.00,
        'status'                => 'Pending',
    ]);

    if (!empty($validated['budget']) && $validated['budget'] > 0) {
        DB::table('budgets_tbl')->insert([
            'project_id' => $projectId,
            'budget_amount' => $validated['budget'],
            'actual_amount' => 0,
        ]);
    }

    $project = DB::table('project_tbl as p')
        ->leftJoin('budgets_tbl as b', 'p.project_id', '=', 'b.project_id')
        ->select(
            'p.*',
            DB::raw('COALESCE(b.budget_amount, 0) as budget')
        )
        ->where('p.project_id', $projectId)
        ->first();

    return response()->json($project, 201);
});

Route::put('/projects/{id}', function (Request $request, $id) {
    $existingProject = DB::table('project_tbl')->where('project_id', $id)->first();
    if (!$existingProject) {
        return response()->json(['message' => 'Project not found'], 404);
    }

    $validated = $request->validate([
        'project_name'          => ['sometimes', 'required', 'string', 'max:100'],
        'client_name'           => ['sometimes', 'required', 'string', 'max:100'],
        'project_manager'       => ['sometimes', 'required', 'string', 'max:100'],
        'start_date'            => ['sometimes', 'required', 'date'],
        'estimated_end_date'    => ['sometimes', 'required', 'date'],
        'actual_end_date'       => ['nullable', 'date'],
        'worker_count'          => ['sometimes', 'required', 'integer', 'min:0'],
        'phase'                 => ['sometimes', 'required', 'string', 'in:Planning,Foundation,Structure,Finishing,Complete'],
        'status'                => ['sometimes', 'required', 'string', 'in:Pending,On Track,At Risk,Delayed,Completed'],
        'completion_percentage' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
        'budget'                => ['nullable', 'numeric', 'min:0'],
    ]);

    $projectData = array_intersect_key($validated, array_flip([
        'project_name', 'client_name', 'project_manager', 'start_date',
        'estimated_end_date', 'actual_end_date', 'worker_count',
        'phase', 'status', 'completion_percentage'
    ]));

    if (!empty($projectData)) {
        DB::table('project_tbl')->where('project_id', $id)->update($projectData);
    }

    $oldStatus = strtolower((string) $existingProject->status);

    DB::table('project_tbl')->where('project_id', $id)->update($validated);

    $project = DB::table('project_tbl')->where('project_id', $id)->first();

    if (array_key_exists('status', $validated)) {
        $newStatus = strtolower((string) $validated['status']);

        if ($newStatus !== $oldStatus) {
            $notifications = app(NotificationService::class);

            if ($newStatus === 'delayed' && !$notifications->alreadyNotified('project_delayed', 'project', (int) $id)) {
                $notifications->notify(
                    title: 'Project Delayed',
                    message: "\"{$project->project_name}\" has been marked as delayed.",
                    type: 'project_delayed',
                    kind: 'overdue',
                    filter: 'alerts',
                    referenceType: 'project',
                    referenceId: (int) $id,
                );
            } elseif ($newStatus === 'at risk' && !$notifications->alreadyNotified('project_at_risk', 'project', (int) $id)) {
                $notifications->notify(
                    title: 'Project At Risk',
                    message: "\"{$project->project_name}\" is now flagged as at risk.",
                    type: 'project_at_risk',
                    kind: 'warning',
                    filter: 'alerts',
                    referenceType: 'project',
                    referenceId: (int) $id,
                );
            }
        }
    }

    return response()->json($project);
});

Route::delete('/projects/{id}', function ($id) {
    $exists = DB::table('project_tbl')->where('project_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Project not found'], 404);
    }

    $hasBudgetAllocation = DB::table('budgets_tbl')
        ->where('project_id', $id)
        ->where('budget_amount', '>', 0)
        ->exists();
    $hasExpenses = DB::table('fin_expense_tbl')
        ->where('project_id', $id)
        ->where('amount', '>', 0)
        ->exists();

    if ($hasBudgetAllocation || $hasExpenses) {
        $relatedRecords = match (true) {
            $hasBudgetAllocation && $hasExpenses => 'budget allocation and expense records',
            $hasBudgetAllocation => 'a budget allocation',
            default => 'expense records',
        };

        return response()->json([
            'message' => "Project cannot be deleted because it has {$relatedRecords}. Remove the related records first.",
        ], 409);
    }

    DB::transaction(function () use ($id) {
        DB::table('expense_tbl')
            ->where('project_id', $id)
            ->whereRaw('COALESCE(labor_amount, 0) + COALESCE(material_amount, 0) + COALESCE(equipment_amount, 0) + COALESCE(other_amount, 0) = 0')
            ->delete();
        DB::table('budgets_tbl')
            ->where('project_id', $id)
            ->where(function ($query) {
                $query->whereNull('budget_amount')->orWhere('budget_amount', '<=', 0);
            })
            ->delete();
        DB::table('project_tbl')->where('project_id', $id)->delete();
    });

    return response()->json(['message' => 'Project deleted'], 200);
});

// ─── OLD EXPENSE CATEGORIES (for backward compatibility) ───────
Route::get('/expense-categories', function () {
    return response()->json(
        DB::table('expense_category_tbl')
            ->select('expense_category_id', 'category_name')
            ->get()
    );
});

// ─── BUDGET ROUTES ──────────────────────────────────────────────
Route::get('/budgets', [BudgetController::class, 'index']);
Route::post('/budgets', [BudgetController::class, 'store']);
Route::put('/budgets/{id}', [BudgetController::class, 'update']);
Route::delete('/budgets/{id}', [BudgetController::class, 'destroy']);

// ─── DASHBOARD ROUTES ───────────────────────────────────────────
Route::get('/dashboard', [DashboardController::class, 'index']);

// ─── NOTIFICATION ROUTES ────────────────────────────────────────
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::match(['post', 'put'], '/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::match(['post', 'put'], '/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']);
});

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