<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class SecurityControls
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && strcasecmp((string)$request->user()->status, 'Active') !== 0) {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            return $request->expectsJson() ? response()->json(['message'=>'Your account is inactive. Please contact an administrator.'],401) : redirect('/');
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','SAMEORIGIN');
        $response->headers->set('Referrer-Policy','same-origin');
        $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=()');
        if ($response->getStatusCode() >= 500 && $response instanceof \Illuminate\Http\JsonResponse) {
            $response->setData(['message'=>'The request could not be completed. Please try again or contact an administrator.']);
        }
        return $response;
    }
}
