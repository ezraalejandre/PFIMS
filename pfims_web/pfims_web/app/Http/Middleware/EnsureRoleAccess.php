<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleAccess
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $role = strtolower((string) $request->user()?->role);

        if (!in_array($role, ['admin', 'accounting', 'operations'], true)) {
            abort(403, 'Your account does not have a recognized portal role.');
        }

        if ($allowedRoles === []) {
            $allowedRoles = $this->apiRoles($request);
        }

        if (in_array($role, $allowedRoles, true)) {
            return $next($request);
        }

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

    private function apiRoles(Request $request): array
    {
        $path = ltrim($request->path(), '/');

        if (str_starts_with($path, 'api/config') || $path === 'api/settings') {
            return ['admin'];
        }
        if (str_starts_with($path, 'api/projects') || str_starts_with($path, 'api/inventory') || str_starts_with($path, 'api/suppliers')) {
            if (str_starts_with($path, 'api/projects') && in_array($request->method(), ['GET', 'HEAD'], true)) {
                return ['admin', 'accounting', 'operations'];
            }
            return ['admin', 'operations'];
        }
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
        if (str_starts_with($path, 'api/budgets')) {
            return ['admin', 'accounting'];
        }
        if (str_starts_with($path, 'api/expenses')) {
            return ['admin', 'operations'];
        }

        return ['admin', 'accounting', 'operations'];
    }
}
