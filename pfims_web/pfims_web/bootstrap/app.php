<?php

use App\Http\Middleware\PreventAuthenticatedPageCaching;
use App\Http\Middleware\EnsureRoleAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Set up the checks that run before page requests.
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role.portal' => EnsureRoleAccess::class]);
        $middleware->append(HandleCors::class);
        $middleware->appendToGroup('web', PreventAuthenticatedPageCaching::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityControls::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnforceAccountSession::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\RequireNavigationGrant::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\RejectUnsafeInput::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\RejectUnsafeInput::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\ArchiveDeletedRecords::class);
    })
    // Set how the app handles request errors.
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request) {
            // Return early if the get status code does not equal 419.
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            // Return early if the is check matches.
            if ($request->is('logout')) {
                return redirect()->route('login');
            }

            // Run this step if the is check matches.
            if ($request->is('login')) {
                $message = 'This sign-in page has been open too long. Refresh the page, then try signing in again.';

                // Return early if the expects json and ajax checks match.
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['message' => $message], 419);
                }

                return redirect()->route('login')->withErrors(['email' => $message]);
            }

            return null;
        });
    })->create();
