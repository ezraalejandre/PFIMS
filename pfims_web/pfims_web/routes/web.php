<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MLController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Auth\ForgotPasswordControllerWeb;

// Landing page (login)
Route::get('/', function () {
    return view('landing');
})->name('login');

// ─── DASHBOARD ROUTES (Role-based) ─────────────────────────────
// Admin Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('admin.dashboard');

// Accounting Dashboard
Route::get('/adashboard', function () {
    return view('Adashboard');
})->middleware('auth')->name('accounting.dashboard');

// Operations Dashboard
Route::get('/odashboard', function () {
    return view('Odashboard');
})->middleware('auth')->name('operations.dashboard');

// ─── ACCOUNTING ROUTES ──────────────────────────────────────────
Route::get('/afinance', function () {
    return view('Afinance');
})->middleware('auth');

Route::get('/areports', function () {
    return view('Areports');
})->middleware('auth');

Route::get('/anotifications', function () {
    return view('Anotifications');
})->middleware('auth');

Route::get('/aprofile', function () {
    return view('Aprofile', [
        'user' => Auth::user(),
    ]);
})->middleware('auth');

Route::get('/asettings', function () {
    $users = User::orderBy('name')->get();
    return view('Asettings', [
        'users' => $users,
    ]);
})->middleware('auth');

// ─── OPERATIONS ROUTES ──────────────────────────────────────────
Route::get('/oprojects', function () {
    return view('Oprojects');
})->middleware('auth');

Route::get('/oinventory', function () {
    return view('Oinventory');
})->middleware('auth');

Route::get('/osuppliers', function () {
    return view('Osuppliers');
})->middleware('auth');

Route::get('/oreports', function () {
    return view('Oreports');
})->middleware('auth');

Route::get('/onotifications', function () {
    return view('Onotifications');
})->middleware('auth');

Route::get('/oprofile', function () {
    return view('Oprofile', [
        'user' => Auth::user(),
    ]);
})->middleware('auth');

Route::get('/osettings', function () {
    $users = User::orderBy('name')->get();
    return view('Osettings', [
        'users' => $users,
    ]);
})->middleware('auth');

// Project Tracking page
Route::get('/projects', function () {
    return view('projtracking');
})->middleware('auth');

Route::delete('/api/projects/{id}', function ($id) {
    $exists = DB::table('project_tbl')->where('project_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Project not found'], 404);
    }

    DB::table('project_tbl')->where('project_id', $id)->delete();
    return response()->json(['message' => 'Project deleted successfully']);
});

// Finance page
Route::get('/finance', function () {
    return view('finance');
})->middleware('auth');

// Budget page
Route::delete('/api/budgets/{id}', function ($id) {
    $exists = DB::table('budgets_tbl')->where('budget_id', $id)->exists();
    if (!$exists) {
        return response()->json(['message' => 'Budget not found'], 404);
    }

    DB::table('budgets_tbl')->where('budget_id', $id)->delete();
    return response()->json(['message' => 'Budget deleted successfully']);
});

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
    // Route::get('/api/suppliers', [SupplierController::class, 'index']);
    // Route::post('/api/suppliers', [SupplierController::class, 'store']);
    // Route::get('/api/suppliers/{id}', [SupplierController::class, 'show']);
    // Route::patch('/api/suppliers/{id}', [SupplierController::class, 'update']);
    // Route::delete('/api/suppliers/{id}', [SupplierController::class, 'destroy']);

    // Config API endpoints
    Route::get('/api/config/{type}', [ConfigController::class, 'index']);
    Route::post('/api/config/{type}', [ConfigController::class, 'store']);
    Route::patch('/api/config/{type}/{id}', [ConfigController::class, 'update']);
    Route::delete('/api/config/{type}/{id}', [ConfigController::class, 'destroy']);

    // // Inventory API endpoints
    Route::get('/api/inventory', [InventoryController::class, 'index']);
    Route::get('/api/inventory/lookup-data', [InventoryController::class, 'getLookupData']);
    Route::post('/api/inventory/item', [InventoryController::class, 'storeItem']);
    Route::post('/api/inventory/transaction', [InventoryController::class, 'addTransaction']);
    Route::patch('/api/inventory/transaction/{id}', [InventoryController::class, 'updateTransaction']);
    Route::delete('/api/inventory/transaction/{id}', [InventoryController::class, 'destroyTransaction']);
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

    $wantsJson = $request->expectsJson() || $request->ajax();

    $dashboardPath = function ($role) {
        $role = strtolower($role ?? '');
        if ($role === 'accounting') {
            return '/adashboard';
        }
        if ($role === 'operations') {
            return '/odashboard';
        }
        return '/dashboard';
    };

    if (Auth::attempt($credentials)) {
        $user = Auth::user();
        if ($user->status !== 'Active') {
            Auth::logout();
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated.',
                ], 403);
            }
            return back()->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }
        $request->session()->regenerate();

        $redirectPath = $dashboardPath($user->role ?? '');

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'redirect' => url($redirectPath),
            ]);
        }

        return redirect($redirectPath);
    }

    if ($wantsJson) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password.',
        ], 422);
    }

    return back()->withErrors([
        'email' => 'Invalid email or password.',
    ]);
});

Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
});

// Change password route
Route::post('/change-password', [PasswordController::class, 'update'])->middleware('auth')->name('password.update');

// ─── MACHINE LEARNING ROUTES (NO AUTH REQUIRED) ────────────────
Route::get('/ml-dashboard-test', function () {
    return view('ml-dashboard-test');
});

// Test endpoints
Route::get('/api/ml/test', [MLController::class, 'test']);
Route::get('/api/ml/test-service', [MLController::class, 'testService']);

// Predictive Analytics - GET for testing, POST for production
Route::post('/api/ml/predict/cost', [MLController::class, 'predictProjectCost']);
Route::get('/api/ml/predict/cost', [MLController::class, 'predictProjectCost']); // Also allow GET for testing

Route::get('/api/ml/predict/material-demand', [MLController::class, 'predictMaterialDemand']);

// Model Management
Route::post('/api/ml/retrain', [MLController::class, 'retrain']);
Route::get('/api/ml/retrain', [MLController::class, 'retrain']); // Also allow GET for testing

Route::get('/api/ml/status', [MLController::class, 'status']);

// Analytics
Route::get('/api/ml/analytics/dashboard', [MLController::class, 'dashboardAnalytics']);
Route::get('/api/ml/analytics/budget-variance', [MLController::class, 'budgetVariance']);

// ─── TEST SERVICE ROUTE ──────────────────────────────────────────
Route::get('/api/ml/test-service', [MLController::class, 'testService']);

Route::get('/ml-debug', function () {
    try {
        $mlService = new \App\Services\MLService();
        $metrics = $mlService->getModelMetrics();
        return response()->json([
            'success' => true,
            'metrics' => $metrics
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Report routes - Using web.php with JSON responses
Route::middleware(['auth'])->group(function () {
    // Page routes
    Route::get('/reports', function () {
        return view('reports');
    })->name('reports');
    
    Route::get('/areports', function () {
        return view('areports');
    })->name('accounting.reports');
    
    Route::get('/oreports', function () {
        return view('oreports');
    })->name('operations.reports');
    
    // API routes for reports (keep in web.php but return JSON)
    Route::get('/api/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/api/reports/upload', [ReportController::class, 'upload'])->name('reports.upload');
    Route::get('/api/reports/download/{id}', [ReportController::class, 'download'])->name('reports.download');
    Route::delete('/api/reports/{id}', [ReportController::class, 'destroy'])->name('reports.destroy');
});

Route::post('/forgot-password/send-otp', [ForgotPasswordControllerWeb::class, 'sendOtp'])
    ->middleware('throttle:5,1') // max 5 requests per minute per IP
    ->name('password.send-otp');
 
Route::post('/forgot-password/verify-otp', [ForgotPasswordControllerWeb::class, 'verifyOtp'])
    ->middleware('throttle:10,1')
    ->name('password.verify-otp');
 
Route::post('/forgot-password/reset', [ForgotPasswordControllerWeb::class, 'resetPassword'])
    ->middleware('throttle:10,1')
    ->name('password.reset');
