<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

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

