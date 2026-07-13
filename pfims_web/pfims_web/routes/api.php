<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\SupplierController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        "message" => "Laravel API connected"
    ]);
});

Route::post('/login', [AuthController::class,'login']);

Route::post('/profile', [AuthController::class,'profile']);

Route::post('/profile/photo', [AuthController::class,'uploadProfilePhoto']);

Route::post('/change-password', [AuthController::class,'changePassword']);

Route::post('/inventory-transactions', [InventoryTransactionController::class, 'store']);

Route::get('/inventory-categories', function () {
    return response()->json(
        DB::table('inventory_category_tbl')
            ->select('inventory_category_id', 'inventory_category_name')
            ->get()
    );
});

Route::get('/units', function () {
    return response()->json(
        DB::table('unit_tbl')
            ->select('unit_id', 'unit_name')
            ->get()
    );
});

// ─── PROJECTS ROUTES ─────────────────────────────────────────────

// GET /api/projects - Get all projects with their budgets
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

// GET /api/projects/list - Simplified list for dropdowns
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

// POST /api/projects - Create new project
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

    // Create budget entry if budget is provided
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

// PUT /api/projects/{id} - Update project
Route::put('/projects/{id}', function (Request $request, $id) {
    $exists = DB::table('project_tbl')->where('project_id', $id)->exists();
    if (!$exists) {
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

    // Update project fields
    $projectData = array_intersect_key($validated, array_flip([
        'project_name', 'client_name', 'project_manager', 'start_date',
        'estimated_end_date', 'actual_end_date', 'worker_count',
        'phase', 'status', 'completion_percentage'
    ]));

    if (!empty($projectData)) {
        DB::table('project_tbl')->where('project_id', $id)->update($projectData);
    }

    // Update budget
    if (array_key_exists('budget', $validated)) {
        $budgetExists = DB::table('budgets_tbl')->where('project_id', $id)->exists();
        if ($budgetExists) {
            DB::table('budgets_tbl')
                ->where('project_id', $id)
                ->update(['budget_amount' => $validated['budget']]);
        } else {
            DB::table('budgets_tbl')->insert([
                'project_id' => $id,
                'budget_amount' => $validated['budget'],
                'actual_amount' => 0,
            ]);
        }
    }

    $project = DB::table('project_tbl as p')
        ->leftJoin('budgets_tbl as b', 'p.project_id', '=', 'b.project_id')
        ->select(
            'p.*',
            DB::raw('COALESCE(b.budget_amount, 0) as budget')
        )
        ->where('p.project_id', $id)
        ->first();

    return response()->json($project);
});

Route::delete('/projects/{id}', function ($id) {
    $exists = DB::table('project_tbl')->where('project_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Project not found'], 404);
    }

    DB::table('project_tbl')->where('project_id', $id)->delete();

    return response()->json(['message' => 'Project deleted'], 200);
});

/*
|--------------------------------------------------------------------------
| Inventory items
|--------------------------------------------------------------------------
*/

Route::get('/inventory-items', function (Request $request) {
    $query = DB::table('inventory_item_tbl')
        ->select('item_id', 'item_name', 'inventory_category_id', 'supplier_id', 'unit_id', 'current_stock');

    if ($request->has('category_id')) {
        $query->where('inventory_category_id', $request->category_id);
    }
    if ($request->has('supplier_id')) {
        $query->where('supplier_id', $request->supplier_id);
    }

    return response()->json($query->get());
});

Route::post('/inventory-items', function (Request $request) {
    $id = DB::table('inventory_item_tbl')->insertGetId([
        'item_name'               => $request->item_name,
        'inventory_category_id'   => $request->inventory_category_id,
        'supplier_id'             => $request->supplier_id,
        'unit_id'                 => $request->unit_id,
        'current_stock'           => $request->current_stock,
        'reorder_level'           => $request->reorder_level ?? 0,
    ]);

    return response()->json(['item_id' => $id], 201);
});

Route::put('/inventory-items/{id}', function (Request $request, $id) {
    $exists = DB::table('inventory_item_tbl')->where('item_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Item not found'], 404);
    }

    $data = [];
    foreach ([
        'item_name'             => 'item_name',
        'inventory_category_id' => 'inventory_category_id',
        'supplier_id'           => 'supplier_id',
        'unit_id'                => 'unit_id',
        'current_stock'          => 'current_stock',
        'reorder_level'           => 'reorder_level',
    ] as $requestKey => $column) {
        if ($request->has($requestKey)) {
            $data[$column] = $request->input($requestKey);
        }
    }

    if (empty($data)) {
        return response()->json(['message' => 'No fields to update'], 422);
    }

    DB::table('inventory_item_tbl')->where('item_id', $id)->update($data);

    $item = DB::table('inventory_item_tbl')->where('item_id', $id)->first();
    return response()->json($item);
});

Route::delete('/inventory-items/{id}', function ($id) {
    $exists = DB::table('inventory_item_tbl')->where('item_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Item not found'], 404);
    }

    DB::table('inventory_transaction_tbl')->where('item_id', $id)->delete();
    DB::table('inventory_item_tbl')->where('item_id', $id)->delete();

    return response()->json(['message' => 'Item deleted'], 200);
});

// ─── EXPENSES ROUTES ─────────────────────────────────────────────

Route::get('/expense-categories', function () {
    return response()->json(
        DB::table('expense_category_tbl')
            ->select('expense_category_id', 'category_name')
            ->get()
    );
});

// GET /api/expenses - Get expenses with proper calculations
Route::get('/expenses', function () {
    return response()->json(
        DB::table('expense_tbl as e')
            ->leftJoin('project_tbl as p', 'e.project_id', '=', 'p.project_id')
            ->leftJoin('expense_category_tbl as c', 'e.expense_category_id', '=', 'c.expense_category_id')
            ->select(
                'e.expense_id',
                'e.project_id',
                'p.project_name',
                'e.expense_description',
                'e.expense_category_id',
                'c.category_name as expense_category_name',
                'e.labor_amount',
                'e.material_amount',
                'e.equipment_amount',
                'e.other_amount',
                DB::raw('COALESCE(e.labor_amount, 0) + COALESCE(e.material_amount, 0) + COALESCE(e.equipment_amount, 0) + COALESCE(e.other_amount, 0) as actual_amount'),
                'e.expense_date',
                'e.remarks'
            )
            ->get()
    );
});

// POST /api/expenses - Create expense
Route::post('/expenses', function (Request $request) {
    $validated = $request->validate([
        'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
        'expense_category_id' => ['required', 'integer', 'exists:expense_category_tbl,expense_category_id'],
        'expense_description' => ['required', 'string', 'max:255'],
        'labor_amount' => ['nullable', 'numeric', 'min:0'],
        'material_amount' => ['nullable', 'numeric', 'min:0'],
        'equipment_amount' => ['nullable', 'numeric', 'min:0'],
        'other_amount' => ['nullable', 'numeric', 'min:0'],
        'expense_date' => ['required', 'date'],
        'remarks' => ['nullable', 'string'],
    ]);

    // At least one amount must be provided
    if (empty($validated['labor_amount']) && empty($validated['material_amount']) && 
        empty($validated['equipment_amount']) && empty($validated['other_amount'])) {
        return response()->json(['message' => 'At least one amount must be provided'], 422);
    }

    $expenseId = DB::table('expense_tbl')->insertGetId([
        'project_id' => $validated['project_id'] ?? null,
        'expense_category_id' => $validated['expense_category_id'],
        'expense_description' => $validated['expense_description'],
        'labor_amount' => $validated['labor_amount'] ?? 0,
        'material_amount' => $validated['material_amount'] ?? 0,
        'equipment_amount' => $validated['equipment_amount'] ?? 0,
        'other_amount' => $validated['other_amount'] ?? 0,
        'expense_date' => $validated['expense_date'],
        'remarks' => $validated['remarks'] ?? null,
    ]);

    $expense = DB::table('expense_tbl as e')
        ->leftJoin('project_tbl as p', 'e.project_id', '=', 'p.project_id')
        ->leftJoin('expense_category_tbl as c', 'e.expense_category_id', '=', 'c.expense_category_id')
        ->select(
            'e.*',
            'p.project_name',
            'c.category_name as expense_category_name',
            DB::raw('COALESCE(e.labor_amount, 0) + COALESCE(e.material_amount, 0) + COALESCE(e.equipment_amount, 0) + COALESCE(e.other_amount, 0) as actual_amount')
        )
        ->where('e.expense_id', $expenseId)
        ->first();

    return response()->json($expense, 201);
});

// PUT /api/expenses/{id} - Update expense
Route::put('/expenses/{id}', function (Request $request, $id) {
    $exists = DB::table('expense_tbl')->where('expense_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Expense not found'], 404);
    }

    $data = [];
    $amountFields = ['labor_amount', 'material_amount', 'equipment_amount', 'other_amount'];
    
    foreach (['project_id', 'expense_category_id', 'expense_description', 'expense_date', 'remarks'] as $field) {
        if ($request->has($field)) {
            $data[$field] = $request->input($field);
        }
    }
    
    foreach ($amountFields as $field) {
        if ($request->has($field)) {
            $data[$field] = $request->input($field) ?? 0;
        }
    }

    if (empty($data)) {
        return response()->json(['message' => 'No fields to update'], 422);
    }

    DB::table('expense_tbl')->where('expense_id', $id)->update($data);

    $expense = DB::table('expense_tbl as e')
        ->leftJoin('project_tbl as p', 'e.project_id', '=', 'p.project_id')
        ->leftJoin('expense_category_tbl as c', 'e.expense_category_id', '=', 'c.expense_category_id')
        ->select(
            'e.*',
            'p.project_name',
            'c.category_name as expense_category_name',
            DB::raw('COALESCE(e.labor_amount, 0) + COALESCE(e.material_amount, 0) + COALESCE(e.equipment_amount, 0) + COALESCE(e.other_amount, 0) as actual_amount')
        )
        ->where('e.expense_id', $id)
        ->first();

    return response()->json($expense);
});

Route::delete('/expenses/{id}', function ($id) {
    $exists = DB::table('expense_tbl')->where('expense_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Expense not found'], 404);
    }

    DB::table('expense_tbl')->where('expense_id', $id)->delete();
    return response()->json(['message' => 'Expense deleted successfully']);
});

Route::get('/inventory-items-list', function () {
    return response()->json(
        DB::table('inventory_item_tbl as i')
            ->join('inventory_category_tbl as c', 'i.inventory_category_id', '=', 'c.inventory_category_id')
            ->join('unit_tbl as u', 'i.unit_id', '=', 'u.unit_id')
            ->leftJoinSub(
                DB::table('inventory_transaction_tbl as t1')
                    ->select('t1.item_id', 't1.transaction_type', 't1.transaction_date')
                    ->whereRaw('t1.transaction_date = (
                        select max(t2.transaction_date) from inventory_transaction_tbl t2
                        where t2.item_id = t1.item_id
                    )'),
                'lt',
                'i.item_id',
                '=',
                'lt.item_id'
            )
            ->select(
                'i.item_id',
                'i.item_name',
                'i.current_stock',
                'c.inventory_category_name',
                'u.unit_name',
                'lt.transaction_type',
                'lt.transaction_date'
            )
            ->get()
    );
});

/*
|--------------------------------------------------------------------------
| Suppliers
|--------------------------------------------------------------------------
*/

Route::get('/suppliers', [SupplierController::class, 'index']);
Route::post('/suppliers', [SupplierController::class, 'store']);
Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
Route::patch('/suppliers/{id}', [SupplierController::class, 'update']);
Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);