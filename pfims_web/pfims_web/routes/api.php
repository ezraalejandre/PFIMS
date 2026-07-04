<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\Auth\ForgotPasswordController;


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

Route::get('/projects', function () {
    return response()->json(
        DB::table('project_tbl')
            ->select('project_id', 'project_name')
            ->get()
    );
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