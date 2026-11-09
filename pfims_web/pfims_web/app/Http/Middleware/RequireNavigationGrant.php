<?php
namespace App\Http\Middleware;
use App\Services\AccountSessionService;
use App\Services\NavigationGrantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// Group the require navigation grant actions.
class RequireNavigationGrant {
    // Run the require navigation grant checks or action.
    public function handle(Request $request,Closure $next){
        // Return early if the user check matches.
        if(!$request->user())return $next($request);
        $html=$request->isMethod('GET') && !$request->expectsJson() && !$request->is('api/*','storage/*','session/*');
        $granted = !$html || app(NavigationGrantService::class)->consume($request);
        // Run this step if the html is set.
        if ($html) $request->session()->put('_page_navigation_granted', $granted);
        // Run this step if the html and granted checks match.
        if(config('session.strict_navigation') && $html && !$granted){
            app(AccountSessionService::class)->release($request->user(),$request->session()->get('account_session_token'));
            Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
            return redirect('/');
        }
        $response=$next($request);
        // Run this step if the response and user checks match.
        if($response instanceof \Illuminate\Http\RedirectResponse && $request->user())app(NavigationGrantService::class)->allow($request,$response->getTargetUrl());
        return $response;
    }
}
