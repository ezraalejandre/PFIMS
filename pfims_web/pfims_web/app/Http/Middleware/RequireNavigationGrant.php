<?php
namespace App\Http\Middleware;
use App\Services\AccountSessionService;
use App\Services\NavigationGrantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class RequireNavigationGrant {
    public function handle(Request $request,Closure $next){
        if(!$request->user())return $next($request);
        $html=$request->isMethod('GET') && !$request->expectsJson() && !$request->is('api/*','storage/*','session/*');
        $granted = !$html || app(NavigationGrantService::class)->consume($request);
        if ($html) $request->session()->put('_page_navigation_granted', $granted);
        if(config('session.strict_navigation') && $html && !$granted){
            app(AccountSessionService::class)->release($request->user(),$request->session()->get('account_session_token'));
            Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
            return redirect('/');
        }
        $response=$next($request);
        if($response instanceof \Illuminate\Http\RedirectResponse && $request->user())app(NavigationGrantService::class)->allow($request,$response->getTargetUrl());
        return $response;
    }
}
