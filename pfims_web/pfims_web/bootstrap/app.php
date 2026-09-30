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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role.portal' => EnsureRoleAccess::class]);
        $middleware->append(HandleCors::class);
        $middleware->appendToGroup('web', PreventAuthenticatedPageCaching::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            if ($request->is('logout')) {
                return redirect()->route('login');
            }

            if ($request->is('login')) {
                $message = 'This sign-in page has been open too long. Refresh the page, then try signing in again.';

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['message' => $message], 419);
                }

                return redirect()->route('login')->withErrors(['email' => $message]);
            }

            return null;
        });
    })->create();
