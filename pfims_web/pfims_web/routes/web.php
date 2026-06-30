<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\InventoryController;

// Landing page (login)
Route::get('/', function () {
    return view('landing');
});

// Dashboard page
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth');

// Project Tracking page
Route::get('/projects', function () {
    return view('projtracking');
})->middleware('auth');

// Finance page
Route::get('/finance', function () {
    return view('finance');
})->middleware('auth');

// Inventory page
Route::get('/inventory', function () {
    return view('inventory');
})->middleware('auth');

// Suppliers page
Route::get('/suppliers', function () {
    return view('suppliers');
})->middleware('auth');

// Supplier API endpoints
Route::middleware('auth')->group(function () {
    Route::get('/api/suppliers', [SupplierController::class, 'index']);
    Route::post('/api/suppliers', [SupplierController::class, 'store']);
    Route::get('/api/suppliers/{id}', [SupplierController::class, 'show']);
    Route::patch('/api/suppliers/{id}', [SupplierController::class, 'update']);

    // Config API endpoints
    Route::get('/api/config/{type}', [ConfigController::class, 'index']);
    Route::post('/api/config/{type}', [ConfigController::class, 'store']);
    Route::patch('/api/config/{type}/{id}', [ConfigController::class, 'update']);
    Route::delete('/api/config/{type}/{id}', [ConfigController::class, 'destroy']);

    // Inventory API endpoints
    Route::get('/api/inventory', [InventoryController::class, 'index']);
    Route::get('/api/inventory/lookup-data', [InventoryController::class, 'getLookupData']);
    Route::post('/api/inventory/item', [InventoryController::class, 'storeItem']);
    Route::post('/api/inventory/transaction', [InventoryController::class, 'addTransaction']);
    Route::get('/api/inventory/transactions', [InventoryController::class, 'getAllTransactions']);
    Route::get('/api/inventory/{itemId}/transactions', [InventoryController::class, 'getTransactions']);
});

// Reports page
Route::get('/reports', function () {
    return view('reports');
})->middleware('auth');

// Notifications page
Route::get('/notifications', function () {
    return view('notifications');
})->middleware('auth');

// Profile page
Route::get('/profile', function () {
    return view('profile', [
        'user' => Auth::user(),
    ]);
})->middleware('auth');

// Route::patch('/profile', function (Request $request) {
//     $user = Auth::user();

//     $validated = $request->validate([
Route::patch('/profile', function (Request $request) {

    /** @var \App\Models\User $user */
    $user = Auth::user();

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        'phone' => ['required', 'string', 'max:50'],
        'location' => ['required', 'string', 'max:255'],
    ]);

    $user->update($validated);

    return redirect('/profile')->with('status', 'Profile updated successfully.');
})->middleware('auth');

// Settings page
Route::get('/settings', function () {
    $users = User::orderBy('name')->get();
    return view('settings', [
        'users' => $users,
    ]);
})->middleware('auth');

// User CRUD endpoints (used by settings page)
Route::post('/users', function (Request $request) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', Rule::unique('users')],
        'role' => ['required', 'in:admin,operations,accounting,Admin,Operations,Accounting'],
        'status' => ['required', 'in:Active,Inactive'],
    ]);

    $role = strtolower($validated['role']);

    $plainPassword = Str::random(12);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'role' => $role,
        'status' => $validated['status'] ?? 'Active',
        'password' => Hash::make($plainPassword),
    ]);

    return response()->json(['success' => true, 'user' => $user, 'password' => $plainPassword]);
})->middleware('auth');

Route::get('/users/{id}', function ($id) {
    $user = User::findOrFail($id);
    return response()->json($user);
})->middleware('auth');

Route::patch('/users/{id}', function (Request $request, $id) {
    $user = User::findOrFail($id);

    $validated = $request->validate([
        'role' => ['required', 'in:admin,operations,accounting,Admin,Operations,Accounting'],
        'status' => ['required', 'in:Active,Inactive'],
    ]);

    $user->role = strtolower($validated['role']);
    $user->status = $validated['status'];
    $user->save();

    return response()->json(['success' => true, 'user' => $user]);
})->middleware('auth');

Route::delete('/users/{id}', function ($id) {
    $user = User::findOrFail($id);
    $user->delete();
    return response()->json(['success' => true]);
})->middleware('auth');

// Redirect login form submission to dashboard
Route::post('/login', function (Request $request) {

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials)) {
        $user = Auth::user();
        if ($user->status !== 'Active') {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    return back()->withErrors([
        'email' => 'Invalid credentials.',
    ]);
});

Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
});