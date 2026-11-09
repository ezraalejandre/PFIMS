<?php

use App\Http\Controllers\Api\FinExpenseController;
use App\Http\Controllers\Auth\ForgotPasswordControllerWeb;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\MLCandidateEvaluationController;
use App\Http\Controllers\MLController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\UserDefaultFilterController;
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

// Dashboard routes (role-based)
// Admin Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'role.portal:admin'])
    ->name('admin.dashboard');

// Accounting Dashboard
Route::get('/adashboard', function () {
    return view('dashboard', ['portal' => 'accounting']);
})->middleware(['auth', 'role.portal:admin,accounting'])->name('accounting.dashboard');

// Operations Dashboard
Route::get('/odashboard', function () {
    return view('dashboard', ['portal' => 'operations']);
})->middleware(['auth', 'role.portal:admin,operations'])->name('operations.dashboard');

// Accounting routes
Route::get('/afinance', function () {
    // Return early if the query equals contracts.
    if (request()->query('section') === 'contracts') return redirect('/afinance?section=budgets&subtab=contracts');
    return view('finance', ['portal' => 'accounting']);
})->middleware(['auth', 'role.portal:admin,accounting']);

// Load requests for /areports.
Route::get('/areports', [ReportController::class, 'page'])->middleware(['auth', 'role.portal:admin,accounting']);

// Load requests for /anotifications.
Route::get('/anotifications', function () {
    return view('notifications', ['portal' => 'accounting']);
})->middleware(['auth', 'role.portal:admin,accounting']);

// Load requests for /aprofile.
Route::get('/aprofile', function () {
    return view('profile', [
        'portal' => 'accounting',
        'user' => Auth::user(),
    ]);
})->middleware(['auth', 'role.portal:admin,accounting']);

// Load requests for /asettings.
Route::get('/asettings', function () {
    /** @var User $currentUser */
    $currentUser = Auth::user();
    $role = strtolower((string) $currentUser->role);
    // Return early if the role does not equal accounting.
    if ($role !== 'accounting') {
        return redirect($role === 'operations' ? '/osettings' : '/settings');
    }
    $users = User::orderBy('name')->get();

    return view('settings', [
        'portal' => 'accounting',
        'users' => $users,
        'loginHistories' => $currentUser->loginHistories()->limit(10)->get(),
    ]);
})->middleware(['auth', 'role.portal:admin,accounting']);

// Operations routes
Route::get('/oprojects', function () {
    return view('projtracking', ['portal' => 'operations']);
})->middleware(['auth', 'role.portal:admin,operations']);

// Load requests for /oinventory.
Route::get('/oinventory', function () {
    return view('inventory', ['portal' => 'operations']);
})->middleware(['auth', 'role.portal:admin,operations']);

// Load requests for /osuppliers.
Route::get('/osuppliers', function () {
    return view('suppliers', ['portal' => 'operations']);
})->middleware(['auth', 'role.portal:admin,operations']);

// Load requests for /oreports.
Route::get('/oreports', [ReportController::class, 'page'])->middleware(['auth', 'role.portal:admin,operations']);

// Load requests for /onotifications.
Route::get('/onotifications', function () {
    return view('notifications', ['portal' => 'operations']);
})->middleware(['auth', 'role.portal:admin,operations']);

// Load requests for /oprofile.
Route::get('/oprofile', function () {
    return view('profile', [
        'portal' => 'operations',
        'user' => Auth::user(),
    ]);
})->middleware(['auth', 'role.portal:admin,operations']);

// Load requests for /osettings.
Route::get('/osettings', function () {
    /** @var User $currentUser */
    $currentUser = Auth::user();
    $role = strtolower((string) $currentUser->role);
    // Return early if the role does not equal operations.
    if ($role !== 'operations') {
        return redirect($role === 'accounting' ? '/asettings' : '/settings');
    }
    $users = User::orderBy('name')->get();

    return view('settings', [
        'portal' => 'operations',
        'users' => $users,
        'loginHistories' => $currentUser->loginHistories()->limit(10)->get(),
    ]);
})->middleware(['auth', 'role.portal:admin,operations']);

// Project Tracking page
Route::get('/projects', function () {
    return view('projtracking', ['portal' => 'admin']);
})->middleware(['auth', 'role.portal:admin']);

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
    // Return early if the query equals contracts.
    if (request()->query('section') === 'contracts') return redirect('/finance?section=budgets&subtab=contracts');
    return view('finance', ['portal' => 'admin']);
})->middleware(['auth', 'role.portal:admin']);

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
    return view('inventory', ['portal' => 'admin']);
})->middleware(['auth', 'role.portal:admin']);

// Suppliers page
Route::get('/suppliers', function () {
    return view('suppliers', ['portal' => 'admin']);
})->middleware(['auth', 'role.portal:admin']);

// Authenticated web-only API endpoints
Route::middleware('auth')->group(function () {
    // Load requests for /api/default-filters.
    Route::get('/api/default-filters', [UserDefaultFilterController::class, 'index']);
    // Update requests for /api/default-filters/{module}.
    Route::put('/api/default-filters/{module}', [UserDefaultFilterController::class, 'update']);
    // Delete requests for /api/default-filters/{module}.
    Route::delete('/api/default-filters/{module}', [UserDefaultFilterController::class, 'destroy']);
    // Config API endpoints
    Route::get('/api/config/{type}', [ConfigController::class, 'index']);
    // Submit requests for /api/config/{type}.
    Route::post('/api/config/{type}', [ConfigController::class, 'store']);
    // Update requests for /api/config/{type}/{id}.
    Route::patch('/api/config/{type}/{id}', [ConfigController::class, 'update']);
    // Delete requests for /api/config/{type}/{id}.
    Route::delete('/api/config/{type}/{id}', [ConfigController::class, 'destroy']);

    // Submit requests for /api/finance-expenses/from-inventory/{transactionId}.
    Route::post('/api/finance-expenses/from-inventory/{transactionId}', [FinExpenseController::class, 'storeFromInventory'])
        ->middleware('role.portal:admin,accounting,operations');

    // Validated, transactional CSV/XLSX imports and downloadable CSV templates.
    Route::post('/api/imports/contracts', [DataImportController::class, 'contracts'])->middleware('throttle:10,1');
    // Submit requests for /api/imports/finance-expenses.
    Route::post('/api/imports/finance-expenses', [DataImportController::class, 'finance'])
        ->middleware('throttle:10,1');
    // Submit requests for /api/imports/inventory.
    Route::post('/api/imports/inventory', [DataImportController::class, 'inventory'])
        ->middleware('throttle:10,1');
    // Submit requests for /api/imports/projects.
    Route::post('/api/imports/projects', [DataImportController::class, 'projects'])
        ->middleware('throttle:10,1');
    // Load requests for /api/imports/templates/{type}.
    Route::get('/api/imports/templates/{type}', [DataImportController::class, 'template'])
        ->whereIn('type', ['projects', 'finance-expenses', 'contracts', 'inventory-items', 'inventory-transactions']);
});

// Load requests for /audit-logs.
Route::get('/audit-logs', [AuditLogController::class, 'index'])
    ->middleware(['auth', 'role.portal:admin'])
    ->name('audit-logs.index');
// Load requests for /audit-logs/latest.
Route::get('/audit-logs/latest', [AuditLogController::class, 'latest'])
    ->middleware(['auth', 'role.portal:admin'])
    ->name('audit-logs.latest');

// Reports page
Route::get('/reports', [ReportController::class, 'page'])->middleware(['auth', 'role.portal:admin']);

// Notifications page
Route::get('/notifications', function () {
    return view('notifications', ['portal' => 'admin']);
})->middleware(['auth', 'role.portal:admin']);

// Profile page
Route::get('/profile', function () {
    return view('profile', [
        'portal' => 'admin',
        'user' => Auth::user(),
    ]);
})->middleware(['auth', 'role.portal:admin']);

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
    // Return early if the expects json check matches.
    if ($request->expectsJson()) return response()->json(['success'=>true,'user'=>$user->only(['name','email','phone','location'])]);

    $role = strtolower($user->role ?? '');
    $redirectPath = $role === 'accounting' ? '/aprofile' : ($role === 'operations' ? '/oprofile' : '/profile');

    return redirect($redirectPath)->with('status', 'Profile updated successfully.');
})->middleware('auth');

// Settings page
Route::get('/settings', function () {
    /** @var User $currentUser */
    $currentUser = Auth::user();
    $role = strtolower((string) $currentUser->role);
    // Return early if the role does not equal admin.
    if ($role !== 'admin') {
        return redirect($role === 'accounting' ? '/asettings' : '/osettings');
    }
    $users = User::orderBy('name')->get();

    return view('settings', [
        'portal' => 'admin',
        'users' => $users,
        'loginHistories' => $currentUser->loginHistories()->limit(10)->get(),
    ]);
})->middleware('auth');

// User CRUD endpoints (used by settings page)
Route::get('/users', function () {
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    return response()->json(['data' => User::orderBy('name')->get(['id','name','email','role','status'])]);
})->middleware('auth');

// Submit requests for /users.
Route::post('/users', function (Request $request) {
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $request->validate(['name'=>['sometimes','string'],'email'=>['sometimes','string'],'role'=>['sometimes','string'],'status'=>['sometimes','string']]);
    $request->merge([
        'name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
        'email' => mb_strtolower(trim((string) $request->input('email'))),
        'role' => strtolower(trim((string) $request->input('role'))),
        'status' => $request->input('status', 'Active'),
    ]);
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:150'],
        'email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
        'role' => ['required', 'in:admin,operations,accounting'],
        'status' => ['required', 'in:Active,Inactive'],
    ]);

    $role = strtolower($validated['role']);

    // Initial password is the normalized email address before the domain.
    $plainPassword = explode('@', $validated['email'], 2)[0];

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

// Load requests for /users/{id}.
Route::get('/users/{id}', function ($id) {
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $user = User::findOrFail($id);

    return response()->json($user);
})->middleware('auth')->whereNumber('id');

// Update requests for /users/{id}.
Route::patch('/users/{id}', function (Request $request, $id) {
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $user = User::findOrFail($id);

    $request->validate(['name'=>['sometimes','string'],'email'=>['sometimes','string'],'role'=>['sometimes','string'],'status'=>['sometimes','string']]);
    // The Settings role editor only submits a role. Preserve the existing
    // status when it is omitted instead of rejecting an otherwise valid role
    // update with the required status validation rule.
    $request->merge([
        'name' => preg_replace('/\s+/u', ' ', trim((string) ($request->input('name') ?? $user->name))),
        'email' => mb_strtolower(trim((string) ($request->input('email') ?? $user->email))),
        'role' => strtolower(trim((string) $request->input('role'))),
        'status' => $request->input('status', $user->status ?? 'Active'),
    ]);

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:150'],
        'email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
        'role' => ['required', 'in:admin,operations,accounting'],
        'status' => ['required', 'in:Active,Inactive'],
    ]);

    // Return early if the id and validated status checks match.
    if ((int) Auth::id() === (int) $user->id && $validated['status'] === 'Inactive') {
        return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
    }
    // Return early if the role and validated role checks match.
    if ($user->role === 'admin' && ($validated['role'] !== 'admin' || $validated['status'] !== 'Active')
        && User::where('role', 'admin')->where('status', 'Active')->count() <= 1) {
        return response()->json(['message' => 'At least one active administrator is required.'], 422);
    }

    $user->name = $validated['name'];
    $user->email = $validated['email'];
    $user->role = strtolower($validated['role']);
    $user->status = $validated['status'];
    $user->save();

    return response()->json(['success' => true, 'user' => $user]);
})->middleware('auth')->whereNumber('id');

// Delete requests for /users/{id}.
Route::delete('/users/{id}', function ($id) {
    abort_unless(strtolower((string) Auth::user()?->role) === 'admin', 403);
    $user = User::findOrFail($id);
    // Return early if the value equals id.
    if ((int) Auth::id() === (int) $user->id) {
        return response()->json(['message' => 'You cannot delete your own account.'], 422);
    }
    // Return early if the role and status checks match.
    if ($user->role === 'admin' && $user->status === 'Active'
        && User::where('role', 'admin')->where('status', 'Active')->count() <= 1) {
        return response()->json(['message' => 'At least one active administrator is required.'], 422);
    }
    $user->delete();

    return response()->json(['success' => true]);
})->middleware('auth')->whereNumber('id');

// Redirect login form submission to dashboard
Route::post('/login', function (Request $request) {
    // Run this step if the value check matches.
    if (Auth::check()) {
        $message = 'This browser is already signed in. Sign out first, or use a separate browser profile or private window for another account.';
        return $request->expectsJson() || $request->ajax()
            ? response()->json(['success' => false, 'message' => $message], 409)
            : back()->withErrors(['email' => $message]);
    }
    // Run this step if the input check matches.
    if (is_string($request->input('email'))) {
        $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
    }

    $credentials = $request->validate([
        'email' => ['required', 'string', 'email', 'max:254'],
        'password' => ['required', 'string', 'max:4096'],
    ]);

    $wantsJson = $request->expectsJson() || $request->ajax();

    $dashboardPath = function ($role) {
        $role = strtolower($role ?? '');
        // Return early if the role equals accounting.
        if ($role === 'accounting') {
            return '/adashboard';
        }
        // Return early if the role equals operations.
        if ($role === 'operations') {
            return '/odashboard';
        }

        return '/dashboard';
    };

    $user = User::where('email', $credentials['email'])->first();

    // Run this step if the user and credentials password checks match.
    if ($user && Hash::check($credentials['password'], $user->password)) {
        // Run this step if the status does not equal active.
        if ($user->status !== 'Active') {
            // Return early if the wants json is set.
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

        // Run this step if the first login verification required is set.
        if ($user->first_login_verification_required) {
            Auth::logout();
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $user->forceFill([
                'first_login_otp' => Hash::make($otp),
                'first_login_otp_expires_at' => now()->addMinutes(10),
            ])->save();

            $request->session()->put('first_login_user_id', $user->id);
            Mail::to($user->email)->send(new FirstLoginVerificationMail($otp, $user->name));

            // Run this step if the wants json is set.
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

        $accountToken = app(\App\Services\AccountSessionService::class)->claim($user, $request->session()->get('account_session_token'));
        Auth::login($user);
        $request->session()->put('account_session_token', $accountToken);
        $request->session()->forget('first_login_user_id');
        $request->session()->regenerate();
        $request->session()->put('navigation_epoch', bin2hex(random_bytes(16)));
        LoginHistory::record($user, $request);

        $redirectPath = $dashboardPath($user->role ?? '');
        app(\App\Services\NavigationGrantService::class)->allow($request,$redirectPath);

        // Return early if the wants json is set.
        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'redirect' => url($redirectPath),
            ]);
        }

        return redirect($redirectPath);
    }

    // Return early if the wants json is set.
    if ($wantsJson) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password.',
        ], 422);
    }

    return back()->withErrors([
        'email' => 'Invalid email or password.',
    ]);
})->middleware('throttle:6,1');

// Submit requests for /login/verify-first-login.
Route::post('/login/verify-first-login', function (Request $request) {
    // Return early if the value check matches.
    if (Auth::check()) {
        return response()->json(['success' => false, 'message' => 'This browser is already signed in. Sign out first, or use a separate browser profile or private window for another account.'], 409);
    }
    $validated = $request->validate([
        'otp' => ['required', 'digits:6'],
    ]);

    $userId = $request->session()->get('first_login_user_id');
    $user = $userId ? User::find($userId) : null;

    // Return early if the user and status checks match.
    if (! $user || $user->status !== 'Active' || ! $user->first_login_verification_required || ! $user->first_login_otp) {
        return response()->json([
            'success' => false,
            'message' => 'Your verification session is no longer valid. Please sign in again.',
        ], 401);
    }

    // Return early if the first login otp expires at checks match.
    if (! $user->first_login_otp_expires_at || $user->first_login_otp_expires_at->isPast()) {
        return response()->json([
            'success' => false,
            'message' => 'This code has expired. Please sign in again to receive a new code.',
        ], 410);
    }

    // Return early if the first login otp otp check matches.
    if (! Hash::check($validated['otp'], $user->first_login_otp)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid verification code. Please try again.',
        ], 422);
    }

    $accountToken = app(\App\Services\AccountSessionService::class)->claim($user, $request->session()->get('account_session_token'));
    $user->forceFill([
        'first_login_verification_required' => false,
        'first_login_otp' => null,
        'first_login_otp_expires_at' => null,
        'email_verified_at' => $user->email_verified_at ?? now(),
    ])->save();

    $request->session()->forget('first_login_user_id');
    Auth::login($user);
    $request->session()->put('account_session_token', $accountToken);
    $request->session()->regenerate();
        $request->session()->put('navigation_epoch', bin2hex(random_bytes(16)));
    LoginHistory::record($user, $request);

    $role = strtolower($user->role ?? '');
    $redirectPath = $role === 'accounting' ? '/adashboard' : ($role === 'operations' ? '/odashboard' : '/dashboard');

    app(\App\Services\NavigationGrantService::class)->allow($request,$redirectPath);
    return response()->json([
        'success' => true,
        'redirect' => url($redirectPath),
    ]);
})->middleware('throttle:6,1')->name('login.verify-first-login');

// Submit requests for /login/resend-first-login.
Route::post('/login/resend-first-login', function (Request $request) {
    $userId = $request->session()->get('first_login_user_id');
    $user = $userId ? User::find($userId) : null;

    // Return early if the user and first login verification required checks match.
    if (! $user || ! $user->first_login_verification_required) {
        return response()->json([
            'success' => false,
            'message' => 'Your verification session is no longer valid. Please sign in again.',
        ], 401);
    }

    // Run this step if the first login otp expires at is set.
    if ($user->first_login_otp_expires_at) {
        $nextResendAt = $user->first_login_otp_expires_at->copy()->subMinutes(9);
        // Return early if the next resend at check matches.
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

// Submit requests for /logout.
Route::post('/logout', function (Request $request) {
    app(\App\Services\AccountSessionService::class)->release($request->user(), $request->session()->get('account_session_token'));

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/')
        ->header('Clear-Site-Data', '"cache"')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
});

// Change password route
Route::post('/change-password', [PasswordController::class, 'update'])->middleware('auth')->name('password.update');

// Machine learning routes
// Financial analytics and model details are available only to signed-in users.
Route::middleware('auth')->group(function () {
    // Load requests for /ml-dashboard-test.
    Route::get('/ml-dashboard-test', function (Request $request) {
        $user = Auth::user();
        $role = $user instanceof User ? strtolower((string) $user->role) : '';
        $section = $request->query('section', 'predictive');
        $allowed = $role === 'admin'
            || ($role === 'accounting' && $section === 'budget-comparison')
            || ($role === 'operations' && $section === 'material-projection');
        abort_unless($allowed, 403);

        return view('ml-dashboard-test', ['portal' => $role]);
    });

    // Group routes that share these access rules or URL settings.
    Route::prefix('api/ml')->group(function () {
        // Submit requests for /predict/cost.
        Route::post('/predict/cost', [MLController::class, 'predictProjectCost'])
            ->middleware(['role.portal:admin', 'throttle:60,1']);
        // Load requests for /prediction-projects.
        Route::get('/prediction-projects', [MLController::class, 'predictionProjects'])
            ->middleware('role.portal:admin');
        // Load requests for /predict/material-demand.
        Route::get('/predict/material-demand', [MLController::class, 'predictMaterialDemand'])
            ->middleware('role.portal:admin,operations');
        // Load requests for /status.
        Route::get('/status', [MLController::class, 'status'])
            ->middleware('role.portal:admin');
        // Load requests for /analytics/dashboard.
        Route::get('/analytics/dashboard', [MLController::class, 'dashboardAnalytics'])
            ->middleware('role.portal:admin');
        // Load requests for /analytics/budget-variance.
        Route::get('/analytics/budget-variance', [MLController::class, 'budgetVariance'])
            ->middleware('role.portal:admin,accounting');

        // Submit requests for /evaluate.
        Route::post('/evaluate', [MLCandidateEvaluationController::class, 'store'])
            ->middleware(['role.portal:admin', 'throttle:3,1']);

        // The controller also enforces the administrator role. Training again is POST-only.
        Route::post('/retrain', [MLController::class, 'retrain'])
            ->middleware(['role.portal:admin', 'throttle:3,1']);
    });
});

// Centralized reports: live datasets + system-generated export history.
Route::middleware(['auth'])->group(function () {
    // Load requests for /api/reports.
    Route::get('/api/reports', [ReportController::class, 'index'])->name('reports.index');
    // Load requests for /api/reports/catalog.
    Route::get('/api/reports/catalog', [ReportController::class, 'catalog'])->name('reports.catalog');
    // Load requests for /api/reports/data/{dataset}.
    Route::get('/api/reports/data/{dataset}', [ReportController::class, 'data'])->name('reports.data');
    // Submit requests for /api/reports/preview.
    Route::post('/api/reports/preview', [ReportController::class, 'preview'])->name('reports.preview');
    // Submit requests for /api/reports/export.
    Route::post('/api/reports/export', [ReportController::class, 'export'])->name('reports.export');
    // Load requests for /api/reports/download/{id}.
    Route::get('/api/reports/download/{id}', [ReportController::class, 'download'])->name('reports.download');
    // Delete requests for /api/reports/{id}.
    Route::delete('/api/reports/{id}', [ReportController::class, 'destroy'])->name('reports.destroy');
});

// Submit requests for /forgot-password/send-otp.
Route::post('/forgot-password/send-otp', [ForgotPasswordControllerWeb::class, 'sendOtp'])
    ->middleware('throttle:5,1') // max 5 requests per minute per IP
    ->name('password.send-otp');

// Submit requests for /forgot-password/verify-otp.
Route::post('/forgot-password/verify-otp', [ForgotPasswordControllerWeb::class, 'verifyOtp'])
    ->middleware('throttle:10,1')
    ->name('password.verify-otp');

// Submit requests for /forgot-password/reset.
Route::post('/forgot-password/reset', [ForgotPasswordControllerWeb::class, 'resetPassword'])
    ->middleware('throttle:10,1')
    ->name('password.reset');

// Load requests for /storage/{folder}/{file}.
Route::get('/storage/{folder}/{file}', [\App\Http\Controllers\ProtectedProofController::class,'show'])->whereIn('folder',['proofs','inventory-transaction-proofs'])->middleware(['auth','role.portal']);

// Group routes that share these access rules or URL settings.
Route::middleware('auth')->group(function(){
    // Submit requests for /session/navigate.
    Route::post('/session/navigate',function(Request $request, \App\Services\NavigationGrantService $navigation){
        $data=$request->validate(['target'=>['required','string','max:2048']]);
        $target=\Illuminate\Http\Request::create($data['target'],'GET');
        $route=Route::getRoutes()->match($target);
        abort_unless(in_array('auth',$route->gatherMiddleware(),true),422,'Select a protected page within PFIMS.');
        $navigation->allow($request,$data['target']);return response()->json(['ok'=>true]);
    });
    // Submit requests for /session/resume.
    Route::post('/session/resume',function(Request $request){
        $data=$request->validate(['mode'=>['required','in:navigate,reload']]);
        // Run this step if the data mode and session checks match.
        if($data['mode']!=='reload' && !$request->session()->get('_page_navigation_granted'))abort(401,'Reopened pages require signing in again.');
        $nonce=bin2hex(random_bytes(32));
        DB::table('account_sessions')->where('user_id',$request->user()->id)->where('token_hash',hash('sha256',$request->session()->get('account_session_token')))->update(['page_nonce'=>$nonce,'closing_at'=>null,'expires_at'=>now()->addSeconds((int)config('session.account_lease_seconds'))]);
        $request->session()->forget('_page_navigation_granted');
        return response()->json(['page_nonce'=>$nonce]);
    });
    // Submit requests for /session/close.
    Route::post('/session/close',function(Request $request,\App\Services\AccountSessionService $sessions){
        // Return early if the input and session checks match.
        if(!is_string($request->input('epoch')) || !hash_equals((string)$request->session()->get('navigation_epoch'),$request->input('epoch')))return response()->noContent();
        // Run this step if the boolean check matches.
        if($request->boolean('closing')){
            $nonce=$request->input('page_nonce');
            // Run this step if the nonce check matches.
            if(is_string($nonce))DB::table('account_sessions')->where('user_id',$request->user()->id)->where('page_nonce',$nonce)->update(['closing_at'=>now(),'expires_at'=>now()->addSeconds((int)config('session.close_grace_seconds'))]);
            return response()->noContent();
        }
        $sessions->release($request->user(),$request->session()->get('account_session_token'));
        Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return response()->noContent();
    });
});
