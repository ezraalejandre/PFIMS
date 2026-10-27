<?php
namespace App\Http\Middleware;
use App\Services\AccountSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnforceAccountSession {
    public function handle(Request $request, Closure $next) {
        if($request->user() && !app(AccountSessionService::class)->valid($request->user(),$request->session()->get('account_session_token'))){
            Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
            return $request->expectsJson()?response()->json(['message'=>'Your session has ended. Please sign in again.'],401):redirect('/');
        }
        return $next($request);
    }
}
