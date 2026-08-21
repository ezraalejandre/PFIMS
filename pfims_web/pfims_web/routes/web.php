<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\AppNotification;
use App\Models\LoginHistory;
use App\Mail\FirstLoginVerificationMail;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransactionController;
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
    /** @var User $currentUser */
    $currentUser = Auth::user();
    $users = User::orderBy('name')->get();
    return view('Asettings', [
        'users' => $users,
        'loginHistories' => $currentUser->loginHistories()->limit(10)->get(),
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
    /** @var User $currentUser */
    $currentUser = Auth::user();
    $users = User::orderBy('name')->get();
    return view('Osettings', [
        'users' => $users,
        'loginHistories' => $currentUser->loginHistories()->limit(10)->get(),
    ]);
})->middleware('auth');

// Project Tracking page
Route::get('/projects', function () {
    return view('projtracking');
})->middleware('auth');

// Route::delete('/api/projects/{id}', function ($id) {
//     $exists = DB::table('project_tbl')->where('project_id', $id)->exists();
//     if (!$exists) {
//         return response()->json(['message' => 'Project not found'], 404);
//     }

//     DB::table('project_tbl')->where('project_id', $id)->delete();
//     return response()->json(['message' => 'Project deleted successfully']);
// });

// Finance page
Route::get('/finance', function () {
    return view('finance');
})->middleware('auth');

// // Budget page
// Route::delete('/api/budgets/{id}', function ($id) {
//     $exists = DB::table('budgets_tbl')->where('budget_id', $id)->exists();
//     if (!$exists) {
//         return response()->json(['message' => 'Budget not found'], 404);
//     }

//     DB::table('budgets_tbl')->where('budget_id', $id)->delete();
//     return response()->json(['message' => 'Budget deleted successfully']);
// });

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
    Route::patch('/api/inventory/item/{id}', [InventoryController::class, 'updateItem']);
    Route::delete('/api/inventory/item/{id}', [InventoryController::class, 'destroyItem']);
    Route::post('/api/inventory/transaction', [InventoryController::class, 'addTransaction']);
    Route::patch('/api/inventory/transaction/{id}', [InventoryController::class, 'updateTransaction']);
    Route::delete('/api/inventory/transaction/{id}', [InventoryController::class, 'destroyTransaction']);
    Route::get('/api/inventory/transactions', [InventoryController::class, 'getAllTransactions']);
    Route::get('/api/inventory/{itemId}/transactions', [InventoryController::class, 'getTransactions']);

    Route::post('/api/inventory-transactions', [InventoryTransactionController::class, 'store']);
    Route::get('/api/inventory-transactions', [InventoryTransactionController::class, 'index']);

    Route::get('/api/inventory-categories', function () {
        return response()->json(
            DB::table('inventory_category_tbl')
                ->select('inventory_category_id', 'inventory_category_name')
                ->get()
        );
    });

    Route::get('/api/inventory-items', function (Request $request) {
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

    Route::post('/api/inventory-items', function (Request $request) {
        $id = DB::table('inventory_item_tbl')->insertGetId([
            'item_name' => $request->item_name,
            'inventory_category_id' => $request->inventory_category_id,
            'supplier_id' => $request->supplier_id,
            'unit_id' => $request->unit_id,
            'current_stock' => $request->current_stock,
            'reorder_level' => $request->reorder_level ?? 0,
        ]);

        return response()->json(['item_id' => $id], 201);
    });

    Route::put('/api/inventory-items/{id}', function (Request $request, $id) {
        $exists = DB::table('inventory_item_tbl')->where('item_id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $data = [];
        foreach ([
            'item_name' => 'item_name',
            'inventory_category_id' => 'inventory_category_id',
            'supplier_id' => 'supplier_id',
            'unit_id' => 'unit_id',
            'current_stock' => 'current_stock',
            'reorder_level' => 'reorder_level',
        ] as $requestKey => $column) {
            if ($request->has($requestKey)) {
                $data[$column] = $request->input($requestKey);
            }
        }

        if (empty($data)) {
            return response()->json(['message' => 'No fields to update'], 422);
        }

        DB::table('inventory_item_tbl')->where('item_id', $id)->update($data);

        return response()->json(DB::table('inventory_item_tbl')->where('item_id', $id)->first());
    });

    Route::delete('/api/inventory-items/{id}', function ($id) {
        $exists = DB::table('inventory_item_tbl')->where('item_id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        DB::table('inventory_transaction_tbl')->where('item_id', $id)->delete();
        DB::table('inventory_item_tbl')->where('item_id', $id)->delete();

        return response()->json(['message' => 'Item deleted'], 200);
    });

    Route::get('/api/inventory-items-list', function () {
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

    Route::get('/api/suppliers', [SupplierController::class, 'index']);
    Route::post('/api/suppliers', [SupplierController::class, 'store']);
    Route::get('/api/suppliers/{id}', [SupplierController::class, 'show']);
    Route::patch('/api/suppliers/{id}', [SupplierController::class, 'update']);
    Route::delete('/api/suppliers/{id}', [SupplierController::class, 'destroy']);
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
    /** @var User $currentUser */
    $currentUser = Auth::user();
    $users = User::orderBy('name')->get();
    return view('settings', [
        'users' => $users,
        'loginHistories' => $currentUser->loginHistories()->limit(10)->get(),
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

    // The initial password is the part of the user's email before the domain.
    $plainPassword = strstr($validated['email'], '@', true);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'role' => $role,
        'status' => $validated['status'] ?? 'Active',
        'password' => Hash::make($plainPassword),
        'first_login_verification_required' => true,
    ]);

    AppNotification::create([
        'user_id' => $user->id,
        'title' => 'Change Your Initial Password',
        'message' => 'For your account security, please change your initial password in Settings.',
        'type' => 'password_change_reminder',
        'kind' => 'warning',
        'filter' => 'system',
        'reference_type' => 'user',
        'reference_id' => $user->id,
        'requires_acknowledgement' => true,
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

    $user = User::where('email', $credentials['email'])->first();

    if ($user && Hash::check($credentials['password'], $user->password)) {
        if ($user->status !== 'Active') {
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

        if ($user->first_login_verification_required) {
            Auth::logout();
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $user->forceFill([
                'first_login_otp' => Hash::make($otp),
                'first_login_otp_expires_at' => now()->addMinutes(10),
            ])->save();

            $request->session()->put('first_login_user_id', $user->id);
            Mail::to($user->email)->send(new FirstLoginVerificationMail($otp, $user->name));

            if ($wantsJson) {
                [$emailName, $emailDomain] = explode('@', $user->email, 2);
                $maskedEmail = mb_substr($emailName, 0, min(2, mb_strlen($emailName))).'***@'.$emailDomain;

                return response()->json([
                    'success' => true,
                    'requires_first_login_verification' => true,
                    'masked_email' => $maskedEmail,
                    'message' => 'A 6-digit verification code has been sent to your email.',
                ]);
            }

            return back()->with('first_login_verification', true);
        }

        Auth::login($user);
        $request->session()->forget('first_login_user_id');
        $request->session()->regenerate();
        LoginHistory::record($user, $request);

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

Route::post('/login/verify-first-login', function (Request $request) {
    $validated = $request->validate([
        'otp' => ['required', 'digits:6'],
    ]);

    $userId = $request->session()->get('first_login_user_id');
    $user = $userId ? User::find($userId) : null;

    if (!$user || !$user->first_login_verification_required || !$user->first_login_otp) {
        return response()->json([
            'success' => false,
            'message' => 'Your verification session is no longer valid. Please sign in again.',
        ], 401);
    }

    if (!$user->first_login_otp_expires_at || $user->first_login_otp_expires_at->isPast()) {
        return response()->json([
            'success' => false,
            'message' => 'This code has expired. Please sign in again to receive a new code.',
        ], 410);
    }

    if (!Hash::check($validated['otp'], $user->first_login_otp)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid verification code. Please try again.',
        ], 422);
    }

    $user->forceFill([
        'first_login_verification_required' => false,
        'first_login_otp' => null,
        'first_login_otp_expires_at' => null,
        'email_verified_at' => $user->email_verified_at ?? now(),
    ])->save();

    $request->session()->forget('first_login_user_id');
    Auth::login($user);
    $request->session()->regenerate();
    LoginHistory::record($user, $request);

    $role = strtolower($user->role ?? '');
    $redirectPath = $role === 'accounting' ? '/adashboard' : ($role === 'operations' ? '/odashboard' : '/dashboard');

    return response()->json([
        'success' => true,
        'redirect' => url($redirectPath),
    ]);
})->middleware('throttle:6,1')->name('login.verify-first-login');

Route::post('/login/resend-first-login', function (Request $request) {
    $userId = $request->session()->get('first_login_user_id');
    $user = $userId ? User::find($userId) : null;

    if (!$user || !$user->first_login_verification_required) {
        return response()->json([
            'success' => false,
            'message' => 'Your verification session is no longer valid. Please sign in again.',
        ], 401);
    }

    if ($user->first_login_otp_expires_at) {
        $nextResendAt = $user->first_login_otp_expires_at->copy()->subMinutes(9);
        if (now()->lt($nextResendAt)) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another code.',
                'retry_after' => max(1, (int) ceil(now()->diffInSeconds($nextResendAt, false))),
            ], 429);
        }
    }

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $user->forceFill([
        'first_login_otp' => Hash::make($otp),
        'first_login_otp_expires_at' => now()->addMinutes(10),
    ])->save();

    Mail::to($user->email)->send(new FirstLoginVerificationMail($otp, $user->name));

    return response()->json([
        'success' => true,
        'message' => 'A new 6-digit verification code has been sent.',
        'retry_after' => 60,
    ]);
})->middleware('throttle:6,1')->name('login.resend-first-login');

Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/')
        ->header('Clear-Site-Data', '"cache"')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
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
