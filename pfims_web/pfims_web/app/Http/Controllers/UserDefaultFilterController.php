<?php

namespace App\Http\Controllers;

use App\Models\UserDefaultFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\AuditFeatureSchema;

// Group the user default filter actions.
class UserDefaultFilterController extends Controller
{
    // Set up the values this class needs.
    public function __construct(private readonly AuditFeatureSchema $schema) {}

    private const MODULES = [
        'dashboard', 'projects', 'finance.expenses', 'finance.budgets', 'finance.bonds',
        'inventory.items', 'inventory.transactions', 'suppliers', 'reports',
        'analytics.material', 'analytics.budget', 'audit-logs',
    ];

    // List the user default filter records.
    public function index(Request $request): JsonResponse
    {
        $this->schema->ensure();
        return response()->json($request->user()->defaultFilters()->pluck('filters', 'module'));
    }

    // Save changes to the user default filter record.
    public function update(Request $request, string $module): JsonResponse
    {
        $this->schema->ensure();
        $validated = $request->validate([
            'module' => [Rule::in(self::MODULES)],
            'filters' => ['present', 'array', 'max:30'],
            'filters.*' => ['nullable'],
        ] + ['module' => [Rule::in(self::MODULES)]]);

        abort_unless(in_array($module, self::MODULES, true), 404);
        // Build a result for each record.
        $filters = collect($validated['filters'])->map(function ($value) {
            // Return early if the value is a list.
            if (is_array($value)) return array_values(array_filter($value, fn ($item) => is_scalar($item)));
            return is_scalar($value) ? trim((string) $value) : null;
        })->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])->all();

        $preference = UserDefaultFilter::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => $module],
            ['filters' => $filters]
        );

        return response()->json(['success' => true, 'module' => $module, 'filters' => $preference->filters]);
    }

    // Delete the selected user default filter record.
    public function destroy(Request $request, string $module): JsonResponse
    {
        $this->schema->ensure();
        abort_unless(in_array($module, self::MODULES, true), 404);
        $request->user()->defaultFilters()->where('module', $module)->delete();
        return response()->json(['success' => true]);
    }
}
