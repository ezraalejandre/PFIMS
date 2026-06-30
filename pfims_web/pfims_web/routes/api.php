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



// Route::post('/profile',
//     [AuthController::class,'profile']
// );


// Route::post('/change-password',
//     [AuthController::class,'changePassword']
// );


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

Route::get('/suppliers', function () {
    return response()->json(
        DB::table('supplier_tbl')
            ->select('supplier_id', 'supplier_name')
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

Route::get('/inventory-items-list', function () {
    return response()->json(
        DB::table('inventory_item_tbl as i')
            ->join('inventory_category_tbl as c', 'i.inventory_category_id', '=', 'c.inventory_category_id')
            ->join('unit_tbl as u', 'i.unit_id', '=', 'u.unit_id')
            ->select(
                'i.item_id',
                'i.item_name',
                'i.current_stock',
                'c.inventory_category_name',
                'u.unit_name'
            )
            ->get()
    );
});


Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);