<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    private const SENSITIVE = ['password', 'remember_token', 'first_login_otp', 'first_login_otp_expires_at'];

    private const META = [
        'project_tbl' => ['Projects', 'project_name', '/projects'],
        'budgets_tbl' => ['Budgets', 'budget_id', '/finance?section=budgets'],
        'fin_expense_tbl' => ['Expenses', 'expense_description', '/finance?section=expenses'],
        'inventory_item_tbl' => ['Inventory', 'item_name', '/inventory?section=items'],
        'inventory_transaction_tbl' => ['Inventory Transactions', 'inventory_transaction_id', '/inventory?section=transactions'],
        'supplier_tbl' => ['Suppliers', 'supplier_name', '/suppliers'],
        'fin_cash_position_tbl' => ['Cash Position', 'cash_position_id', '/finance?section=cash-position'],
        'fin_construction_bond_tbl' => ['Construction Bonds', 'bond_provider', '/finance?section=construction-bonds'],
        'fin_receivable_payable_tbl' => ['Receivables and Payables', 'counterparty_name', '/finance?section=receivables-payables'],
        'company_asset_tbl' => ['Company Assets', 'asset_name', '/finance?section=equipment'],
        'fin_equipment_expense_tbl' => ['Equipment Expenses', 'expense_type', '/finance?section=equipment'],
        'fin_equipment_rental_income_tbl' => ['Rental Income', 'rental_income_id', '/finance?section=equipment'],
        'fin_project_contract_tbl' => ['Project Contracts', 'contract_id', '/finance?section=contracts'],
        'reports' => ['Reports', 'title', '/reports'],
        'users' => ['User Management', 'name', '/settings?section=usermanagement'],
        'unit_tbl' => ['Configurations', 'unit_name', '/settings?section=configurations'],
        'inventory_category_tbl' => ['Configurations', 'inventory_category_name', '/settings?section=configurations'],
        'fin_expense_category_tbl' => ['Configurations', 'category_name', '/settings?section=configurations'],
        'project_phase_tbl' => ['Configurations', 'phase_name', '/settings?section=configurations'],
        'fin_component_tbl' => ['Configurations', 'component_name', '/settings?section=configurations'],
        'user_default_filters' => ['Default Filters', 'module', '/settings?section=defaultfilters'],
        'system_settings' => ['System Settings', 'setting_key', '/settings?section=configurations'],
        'expense_category_tbl' => ['Configurations', 'category_name', '/settings?section=configurations'],
    ];

    public function record(Model $model, string $action, array $before = [], array $after = []): void
    {
        $user = Auth::user();
        if (! $user || ! Schema::hasTable('audit_logs') || $model instanceof AuditLog) {
            return;
        }

        $table = $model->getTable();
        [$module, $labelField, $baseUrl] = self::META[$table] ?? [str($table)->replace('_tbl', '')->headline()->toString(), $model->getKeyName(), null];
        $attributes = $action === 'DELETE' ? $before : $after;
        $label = trim((string) ($attributes[$labelField] ?? $model->getKey() ?? 'record'));
        $changes = $this->meaningfulChanges($before, $after);
        $verb = match ($action) { 'CREATE' => 'Added', 'UPDATE' => 'Updated', default => 'Deleted' };
        $details = "{$verb} {$module}: {$label}";
        if ($action === 'UPDATE' && $changes) {
            $fields = collect(array_keys($changes))->map(fn ($field) => str($field)->replace('_', ' ')->headline())->join(', ');
            $details .= "; changed {$fields}";
        }

        $separator = $baseUrl && str_contains($baseUrl, '?') ? '&' : '?';
        AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name ?: 'Unknown user',
            'user_email' => $user->email ?: '',
            'user_role' => strtoupper((string) ($user->role ?: 'USER')),
            'action_type' => $action,
            'module' => $module,
            'subject_type' => $model::class,
            'subject_table' => $table,
            'subject_key' => $model->getKeyName(),
            'subject_id' => (string) $model->getKey(),
            'record_label' => $label,
            'details' => $details,
            'changes' => $changes ?: null,
            'view_url' => $baseUrl ? $baseUrl.$separator.'audit_subject='.rawurlencode((string) $model->getKey()) : null,
        ]);
    }

    private function meaningfulChanges(array $before, array $after): array
    {
        $before = Arr::except($before, [...self::SENSITIVE, 'created_at', 'updated_at']);
        $after = Arr::except($after, [...self::SENSITIVE, 'created_at', 'updated_at']);
        $changes = [];
        foreach ($after as $field => $value) {
            if (array_key_exists($field, $before) && (string) $before[$field] !== (string) $value) {
                $changes[$field] = ['from' => $before[$field], 'to' => $value];
            }
        }
        return $changes;
    }
}
