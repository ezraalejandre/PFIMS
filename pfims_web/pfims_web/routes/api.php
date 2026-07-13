<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\Api\DashboardController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        "message" => "Laravel API connected"
    ]);
});



Route::post(
    '/login',
    [AuthController::class,'login']
);


Route::post(
'/profile',
[AuthController::class,'profile']
);

Route::post(
    '/profile/photo',
    [AuthController::class,'uploadProfilePhoto']
);


Route::post(
'/change-password',
[AuthController::class,'changePassword']
);




Route::post(
    '/inventory-transactions',
    [InventoryTransactionController::class, 'store']
);

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

Route::get('/expense-categories', function () {
    return response()->json(
        DB::table('expense_category_tbl')
            ->select('expense_category_id', 'category_name')
            ->get()
    );
});

Route::get('/expenses', function () {
    return response()->json(
        DB::table('expense_tbl as e')
            ->leftJoin('project_tbl as p', 'e.project_id', '=', 'p.project_id')
            ->join('expense_category_tbl as c', 'e.expense_category_id', '=', 'c.expense_category_id')
            ->select(
                'e.expense_id',
                'e.project_id',
                'p.project_name',
                'e.expense_category_id',
                'c.category_name',
                'e.expense_description',
                'e.labor_amount',
                'e.material_amount',
                'e.equipment_amount',
                'e.other_amount',
                'e.actual_amount',
                'e.expense_date',
                'e.remarks'
            )
            ->orderByDesc('e.expense_id')
            ->get()
    );
});

Route::get('/projects', function () {
    return response()->json(
        DB::table('project_tbl')
            ->select('project_id', 'project_name')
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
    ]);

    $id = DB::table('project_tbl')->insertGetId([
        'project_name'          => $validated['project_name'],
        'client_name'           => $validated['client_name'],
        'project_manager'       => $validated['project_manager'],
        'start_date'            => $validated['start_date'],
        'estimated_end_date'    => $validated['estimated_end_date'],
        'worker_count'          => $validated['worker_count'] ?? 0,
        // Not collected by the current form yet — sensible defaults for a
        // brand-new project. Adjust if you add fields for these later.
        'phase'                 => 'Planning',
        'completion_percentage' => 0.00,
        'status'                => 'Pending',
    ]);

    $project = DB::table('project_tbl')->where('project_id', $id)->first();

    return response()->json($project, 201);
});

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
    ]);

    if (empty($validated)) {
        return response()->json(['message' => 'No fields to update'], 422);
    }

    DB::table('project_tbl')->where('project_id', $id)->update($validated);

    $project = DB::table('project_tbl')->where('project_id', $id)->first();
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

Route::post('/budgets', function (Request $request) {
    $validated = $request->validate([
        'project_id'    => ['required', 'integer', 'exists:project_tbl,project_id'],
        'budget_amount' => ['required', 'numeric', 'min:0.01'],
    ]);

    // A "budget" row is an expense_tbl row that only carries budget_amount
    // for a project — no category, no actual spend recorded yet. Treat it
    // as one row per project: setting a new budget updates that row
    // instead of inserting a duplicate every time.
    $existing = DB::table('expense_tbl')
        ->where('project_id', $validated['project_id'])
        ->whereNull('expense_category_id')
        ->whereNull('actual_amount')
        ->first();

    if ($existing) {
        DB::table('expense_tbl')
            ->where('expense_id', $existing->expense_id)
            ->update(['budget_amount' => $validated['budget_amount']]);
        $id = $existing->expense_id;
    } else {
        $id = DB::table('expense_tbl')->insertGetId([
            'project_id'    => $validated['project_id'],
            'budget_amount' => $validated['budget_amount'],
            'expense_date'  => now()->toDateString(),
        ]);
    }

    $budget = DB::table('expense_tbl as e')
        ->join('project_tbl as p', 'e.project_id', '=', 'p.project_id')
        ->select('e.expense_id', 'e.project_id', 'p.project_name', 'e.budget_amount')
        ->where('e.expense_id', $id)
        ->first();

    return response()->json($budget, 201);
});

Route::post('/expenses', function (Request $request) {
    $validated = $request->validate([
        'project_id'          => ['nullable', 'integer', 'exists:project_tbl,project_id'],
        'expense_category_id' => ['required', 'integer', 'exists:expense_category_tbl,expense_category_id'],
        'expense_description' => ['required', 'string', 'max:255'],
        'amount'              => ['required', 'numeric', 'min:0.01'],
        'expense_date'        => ['required', 'date'],
        'remarks'             => ['nullable', 'string'],
    ]);

    $category = DB::table('expense_category_tbl')
        ->where('expense_category_id', $validated['expense_category_id'])
        ->first();

    if (!$category) {
        return response()->json(['message' => 'Invalid expense category'], 422);
    }

    // Route the amount into the column matching the chosen category.
    // Anything that isn't Labor/Material/Equipment falls back to "other".
    $amountColumn = match (strtolower(trim($category->category_name))) {
        'labor'     => 'labor_amount',
        'material'  => 'material_amount',
        'equipment' => 'equipment_amount',
        default     => 'other_amount',
    };

    // project_id is optional now — an expense no longer has to belong to
    // a specific project.
    $id = DB::table('expense_tbl')->insertGetId([
        'project_id'          => $validated['project_id'] ?? null,
        'expense_category_id' => $validated['expense_category_id'],
        'expense_description' => $validated['expense_description'],
        $amountColumn          => $validated['amount'],
        'actual_amount'        => $validated['amount'],
        'expense_date'         => $validated['expense_date'],
        'remarks'              => $validated['remarks'] ?? null,
    ]);

    // Joined read-back so the client gets project_name / category_name
    // without a second round trip. leftJoin so project-less expenses
    // still come back instead of vanishing.
    $expense = DB::table('expense_tbl as e')
        ->leftJoin('project_tbl as p', 'e.project_id', '=', 'p.project_id')
        ->join('expense_category_tbl as c', 'e.expense_category_id', '=', 'c.expense_category_id')
        ->select(
            'e.expense_id',
            'e.project_id',
            'p.project_name',
            'e.expense_category_id',
            'c.category_name',
            'e.expense_description',
            'e.labor_amount',
            'e.material_amount',
            'e.equipment_amount',
            'e.other_amount',
            'e.actual_amount',
            'e.expense_date',
            'e.remarks'
        )
        ->where('e.expense_id', $id)
        ->first();

    return response()->json($expense, 201);
});

Route::put('/expenses/{id}', function (Request $request, $id) {
    $exists = DB::table('expense_tbl')->where('expense_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Expense not found'], 404);
    }

    $validated = $request->validate([
        'project_id'          => ['nullable', 'integer', 'exists:project_tbl,project_id'],
        'expense_category_id' => ['required', 'integer', 'exists:expense_category_tbl,expense_category_id'],
        'expense_description' => ['required', 'string', 'max:255'],
        'amount'              => ['required', 'numeric', 'min:0.01'],
        'expense_date'        => ['required', 'date'],
        'remarks'             => ['nullable', 'string'],
    ]);

    $category = DB::table('expense_category_tbl')
        ->where('expense_category_id', $validated['expense_category_id'])
        ->first();

    if (!$category) {
        return response()->json(['message' => 'Invalid expense category'], 422);
    }

    $amountColumn = match (strtolower(trim($category->category_name))) {
        'labor'     => 'labor_amount',
        'material'  => 'material_amount',
        'equipment' => 'equipment_amount',
        default     => 'other_amount',
    };

    // Clear every amount column first, then set only the one matching the
    // (possibly new) category — editing can move an expense from e.g.
    // Labor to Equipment, and the old column shouldn't keep stale data.
    // project_id is optional now, same as on create.
    $data = [
        'project_id'           => $validated['project_id'] ?? null,
        'expense_category_id'  => $validated['expense_category_id'],
        'expense_description'  => $validated['expense_description'],
        'labor_amount'         => null,
        'material_amount'      => null,
        'equipment_amount'     => null,
        'other_amount'         => null,
        'actual_amount'        => $validated['amount'],
        'expense_date'         => $validated['expense_date'],
        'remarks'              => $validated['remarks'] ?? null,
    ];
    $data[$amountColumn] = $validated['amount'];

    DB::table('expense_tbl')->where('expense_id', $id)->update($data);

    $expense = DB::table('expense_tbl as e')
        ->leftJoin('project_tbl as p', 'e.project_id', '=', 'p.project_id')
        ->join('expense_category_tbl as c', 'e.expense_category_id', '=', 'c.expense_category_id')
        ->select(
            'e.expense_id',
            'e.project_id',
            'p.project_name',
            'e.expense_category_id',
            'c.category_name',
            'e.expense_description',
            'e.labor_amount',
            'e.material_amount',
            'e.equipment_amount',
            'e.other_amount',
            'e.actual_amount',
            'e.expense_date',
            'e.remarks'
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

    return response()->json(['message' => 'Expense deleted'], 200);
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

// NEW: update an inventory item. Every field is optional so the client can
// send just the ones it actually changed (e.g. name + stock from the Edit
// Item modal) without clobbering the rest.
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

// NEW: delete an inventory item.
Route::delete('/inventory-items/{id}', function ($id) {
    $exists = DB::table('inventory_item_tbl')->where('item_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Item not found'], 404);
    }

    // If you'd rather keep transaction history for deleted items, remove this
    // line and add an `is_deleted` / soft-delete column to inventory_item_tbl
    // instead of hard-deleting.
    DB::table('inventory_transaction_tbl')->where('item_id', $id)->delete();
    DB::table('inventory_item_tbl')->where('item_id', $id)->delete();

    return response()->json(['message' => 'Item deleted'], 200);
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
| NOTE: this assumes supplier_tbl has: supplier_id, supplier_name,
| contact_number, address, is_active (tinyint/bool). Rename the columns
| below if your actual schema differs.
*/

Route::get('/suppliers', function () {
    return response()->json(
        DB::table('supplier_tbl as s')
            ->leftJoin('inventory_item_tbl as i', 'i.supplier_id', '=', 's.supplier_id')
            ->select(
                's.supplier_id',
                's.supplier_name',
                's.contact_number',
                's.address',
                DB::raw('COUNT(i.item_id) as item_count')
            )
            ->groupBy('s.supplier_id', 's.supplier_name', 's.contact_number', 's.address')
            ->get()
    );
});

Route::post('/suppliers', function (Request $request) {
    $id = DB::table('supplier_tbl')->insertGetId([
        'supplier_name'   => $request->supplier_name,
        'contact_number'  => $request->contact_number,
        'address'         => $request->address,
    ]);

    return response()->json(['supplier_id' => $id], 201);
});

Route::put('/suppliers/{id}', function (Request $request, $id) {
    $exists = DB::table('supplier_tbl')->where('supplier_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Supplier not found'], 404);
    }

    $data = [];
    foreach ([
        'supplier_name'  => 'supplier_name',
        'contact_number' => 'contact_number',
        'address'        => 'address',
    ] as $requestKey => $column) {
        if ($request->has($requestKey)) {
            $data[$column] = $request->input($requestKey);
        }
    }

    if (empty($data)) {
        return response()->json(['message' => 'No fields to update'], 422);
    }

    DB::table('supplier_tbl')->where('supplier_id', $id)->update($data);

    $supplier = DB::table('supplier_tbl')->where('supplier_id', $id)->first();
    return response()->json($supplier);
});

Route::delete('/suppliers/{id}', function ($id) {
    $exists = DB::table('supplier_tbl')->where('supplier_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Supplier not found'], 404);
    }

    $hasItems = DB::table('inventory_item_tbl')->where('supplier_id', $id)->exists();
    if ($hasItems) {
        // Prevent orphaning inventory items. The client shows this message
        // back to the user rather than silently failing.
        return response()->json([
            'message' => 'Cannot delete supplier: it is still linked to inventory items.'
        ], 409);
    }

    DB::table('supplier_tbl')->where('supplier_id', $id)->delete();

    return response()->json(['message' => 'Supplier deleted'], 200);
});


Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
Route::get('/inventory-transactions', [InventoryTransactionController::class, 'index']);
Route::get('/budgets', [BudgetController::class, 'index']);
Route::post('/budgets', [BudgetController::class, 'store']);

Route::get('/expenses', [ExpenseController::class, 'index']);
Route::post('/expenses', [ExpenseController::class, 'store']);
Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

Route::get('/dashboard', [DashboardController::class, 'index']);