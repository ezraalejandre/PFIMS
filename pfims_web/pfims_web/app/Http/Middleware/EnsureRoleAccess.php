<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Group the ensure role access actions.
class EnsureRoleAccess
{
    // Run the ensure role access checks or action.
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $role = strtolower((string) $request->user()?->role);

        // Run this step if the role check matches.
        if (!in_array($role, ['admin', 'accounting', 'operations'], true)) {
            abort(403, 'Your account does not have a recognized portal role.');
        }

        // Run this step if the allowed roles is empty.
        if ($allowedRoles === []) {
            $allowedRoles = $this->apiRoles($request);
        }

        // Return early if the allowed roles check matches.
        if (in_array($role, $allowedRoles, true)) {
            return $next($request);
        }

        // Return early if the expects json and is checks match.
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this resource.',
            ], 403);
        }

        $dashboard = match ($role) {
            'accounting' => '/adashboard',
            'operations' => '/odashboard',
            default => '/dashboard',
        };

        return redirect($dashboard);
    }

    // Get the api roles.
    private function apiRoles(Request $request): array
    {
        $path = ltrim($request->path(), '/');

        // Return early if the path checks match.
        if (str_starts_with($path, 'api/config') || $path === 'api/settings') {
            return ['admin'];
        }
        // Run this step if the path checks match.
        if (str_starts_with($path, 'api/projects') || str_starts_with($path, 'api/inventory') || str_starts_with($path, 'api/suppliers')) {
            // Return early if the path and method checks match.
            if (str_starts_with($path, 'api/projects') && in_array($request->method(), ['GET', 'HEAD'], true)) {
                return ['admin', 'accounting', 'operations'];
            }
            return ['admin', 'operations'];
        }
        // Return early if the path checks match.
        if (str_starts_with($path, 'api/finance-')
            || str_starts_with($path, 'api/project-contracts')
            || str_starts_with($path, 'api/receivables-payables')
            || str_starts_with($path, 'api/construction-bonds')
            || str_starts_with($path, 'api/cash-positions')
            || str_starts_with($path, 'api/equipment-')
            || str_starts_with($path, 'api/company-assets')
            || str_starts_with($path, 'api/reports/')
            || $path === 'api/expense-categories'
        ) {
            return ['admin', 'accounting'];
        }
        // Return early if the path check matches.
        if (str_starts_with($path, 'api/budgets')) {
            return ['admin', 'accounting'];
        }
        // Return early if the path check matches.
        if (str_starts_with($path, 'api/expenses')) {
            return ['admin', 'operations'];
        }

        return ['admin', 'accounting', 'operations'];
    }
}
