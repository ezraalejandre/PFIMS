<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    private const DEFAULTS = [
        'inventory_reorder_threshold' => '5',
        'project_at_risk_days_before_end_date' => '7',
        'inventory_max_transaction_quantity' => '999999999.99',
    ];

    public function show(): JsonResponse
    {
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $values[$key] = SystemSetting::value($key, $default);
        }

        return response()->json(['success' => true, 'data' => $values]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless(strtolower((string) $request->user()?->role) === 'admin', 403, 'Only administrators can update system settings.');

        $validated = $request->validate([
            'inventory_reorder_threshold' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'project_at_risk_days_before_end_date' => ['required', 'integer', 'min:0', 'max:365'],
            'inventory_max_transaction_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => (string) $value]
            );
        }

        return response()->json(['success' => true, 'data' => $validated, 'message' => 'System settings saved successfully.']);
    }
}
