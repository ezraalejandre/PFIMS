<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Group the prevent authenticated page caching actions.
class PreventAuthenticatedPageCaching
{
    // Run the prevent authenticated page caching checks or action.
    public function handle(Request $request, Closure $next): Response
    {
        $wasAuthenticated = $request->user() !== null;
        $response = $next($request);

        // Run this step if the was authenticated is set.
        if ($wasAuthenticated) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
