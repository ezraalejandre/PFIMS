<?php

use App\Http\Controllers\Api\FinExpenseController;
use App\Http\Controllers\Auth\ForgotPasswordControllerWeb;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\MLController;
use App\Http\Controllers\ReportController;
use App\Mail\FirstLoginVerificationMail;
use App\Models\AppNotification;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

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

// Authenticated web-only API endpoints
Route::middleware('auth')->group(function () {
    // Config API endpoints
    Route::get('/api/config/{type}', [ConfigController::class, 'index']);
    Route::post('/api/config/{type}', [ConfigController::class, 'store']);
    Route::patch('/api/config/{type}/{id}', [ConfigController::class, 'update']);
    Route::delete('/api/config/{type}/{id}', [ConfigController::class, 'destroy']);

    Route::post('/api/finance-expenses/from-inventory/{transactionId}', [FinExpenseController::class, 'storeFromInventory']);

    // Validated, transactional CSV/XLSX imports and downloadable CSV templates.
    Route::post('/api/imports/finance-expenses', [DataImportController::class, 'finance'])
        ->middleware('throttle:10,1');
    Route::post('/api/imports/inventory', [DataImportController::class, 'inventory'])
        ->middleware('throttle:10,1');
    Route::get('/api/imports/templates/{type}', [DataImportController::class, 'template'])
        ->whereIn('type', ['finance-expenses', 'inventory-items', 'inventory-transactions']);
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

    /** @var User $user */
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
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $request->merge([
        'name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
        'email' => mb_strtolower(trim((string) $request->input('email'))),
        'role' => strtolower(trim((string) $request->input('role'))),
    ]);
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:150'],
        'email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
        'role' => ['required', 'in:admin,operations,accounting'],
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
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $user = User::findOrFail($id);

    return response()->json($user);
})->middleware('auth')->whereNumber('id');

Route::patch('/users/{id}', function (Request $request, $id) {
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $user = User::findOrFail($id);

    $request->merge(['role' => strtolower(trim((string) $request->input('role')))]);

    $validated = $request->validate([
        'role' => ['required', 'in:admin,operations,accounting'],
        'status' => ['required', 'in:Active,Inactive'],
    ]);

    if ((int) Auth::id() === (int) $user->id && $validated['status'] === 'Inactive') {
        return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
    }
    if ($user->role === 'admin' && ($validated['role'] !== 'admin' || $validated['status'] !== 'Active')
        && User::where('role', 'admin')->where('status', 'Active')->count() <= 1) {
        return response()->json(['message' => 'At least one active administrator is required.'], 422);
    }

    $user->role = strtolower($validated['role']);
    $user->status = $validated['status'];
    $user->save();

    return response()->json(['success' => true, 'user' => $user]);
})->middleware('auth')->whereNumber('id');

Route::delete('/users/{id}', function ($id) {
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $user = User::findOrFail($id);
    if ((int) Auth::id() === (int) $user->id) {
        return response()->json(['message' => 'You cannot delete your own account.'], 422);
    }
    if ($user->role === 'admin' && $user->status === 'Active'
        && User::where('role', 'admin')->where('status', 'Active')->count() <= 1) {
        return response()->json(['message' => 'At least one active administrator is required.'], 422);
    }
    $user->delete();

    return response()->json(['success' => true]);
})->middleware('auth')->whereNumber('id');

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

    if (! $user || ! $user->first_login_verification_required || ! $user->first_login_otp) {
        return response()->json([
            'success' => false,
            'message' => 'Your verification session is no longer valid. Please sign in again.',
        ], 401);
    }

    if (! $user->first_login_otp_expires_at || $user->first_login_otp_expires_at->isPast()) {
        return response()->json([
            'success' => false,
            'message' => 'This code has expired. Please sign in again to receive a new code.',
        ], 410);
    }

    if (! Hash::check($validated['otp'], $user->first_login_otp)) {
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

    if (! $user || ! $user->first_login_verification_required) {
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

// ─── MACHINE LEARNING ROUTES ────────────────────────────────────
// Financial analytics and model details are available only to signed-in users.
Route::middleware('auth')->group(function () {
    Route::get('/ml-dashboard-test', function () {
        $user = Auth::user();
        abort_unless($user instanceof User && in_array(strtolower((string) $user->role), ['admin', 'accounting'], true), 403);

        return view('ml-dashboard-test');
    });

    Route::prefix('api/ml')->group(function () {
        Route::post('/predict/cost', [MLController::class, 'predictProjectCost'])
            ->middleware('throttle:60,1');
        Route::get('/prediction-projects', [MLController::class, 'predictionProjects']);
        Route::get('/predict/material-demand', [MLController::class, 'predictMaterialDemand']);
        Route::get('/status', [MLController::class, 'status']);
        Route::get('/analytics/dashboard', [MLController::class, 'dashboardAnalytics']);
        Route::get('/analytics/budget-variance', [MLController::class, 'budgetVariance']);

        // The controller also enforces the administrator role. Retraining is POST-only.
        Route::post('/retrain', [MLController::class, 'retrain'])
            ->middleware('throttle:3,1');
    });
});

// Centralized reports: live datasets + system-generated export history.
Route::middleware(['auth'])->group(function () {
    Route::get('/api/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/api/reports/catalog', [ReportController::class, 'catalog'])->name('reports.catalog');
    Route::get('/api/reports/data/{dataset}', [ReportController::class, 'data'])->name('reports.data');
    Route::post('/api/reports/export', [ReportController::class, 'export'])->name('reports.export');
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
