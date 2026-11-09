<?php
namespace App\Http\Middleware;
use App\Services\AccountSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// Group the enforce account session actions.
class EnforceAccountSession {
    // Run the enforce account session checks or action.
    public function handle(Request $request, Closure $next) {
        // Run this step if the user and session checks match.
        if($request->user() && !app(AccountSessionService::class)->valid($request->user(),$request->session()->get('account_session_token'))){
            $seconds = app(AccountSessionService::class)->inactivityTimeoutSeconds($request->user(), $request->session()->get('account_session_token'));
            Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
            // Run this step if the seconds is not null.
            if ($seconds !== null) $request->session()->flash('inactivity_timeout_seconds', $seconds);
            return $request->expectsJson()
                ? response()->json(['message' => 'Your session has ended. Please sign in again.',
                    'reason' => $seconds !== null ? 'inactivity' : 'session_ended', 'inactivity_seconds' => $seconds], 401)
                : redirect('/');
        }
        return $next($request);
    }
}
