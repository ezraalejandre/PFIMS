<?php
namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrashService
{
    // Parent-first restoration order; no session, OTP, token, audit or queue resurrection.
    public const TABLES = [
        'users', 'user_tbl', 'unit_tbl', 'inventory_category_tbl', 'supplier_tbl',
        'fin_expense_category_tbl', 'expense_category_tbl', 'fin_component_tbl',
        'project_manager_tbl', 'project_phase_tbl', 'project_tbl', 'company_asset_tbl',
        'company_bank_account_tbl', 'inventory_item_tbl', 'inventory_transaction_tbl',
        'fin_expense_tbl', 'expense_tbl', 'budgets_tbl', 'fin_project_contract_tbl',
        'fin_construction_bond_tbl', 'fin_receivable_payable_tbl', 'fin_cash_position_tbl',
        'fin_equipment_expense_tbl', 'fin_equipment_rental_income_tbl',
        'inventory_cost_allocation_tbl', 'project_budget_history', 'ml_project_cost_snapshots',
        'reports', 'notifications_tbl', 'user_default_filters',
    ];

    public function snapshot(): array
    {
        $snapshot = [];
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) continue;
            $key = collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'] ?? [];
            if (count($key) !== 1) throw new \RuntimeException('Trash requires a single primary key: '.$table);
            $snapshot[$table] = ['key' => $key[0], 'rows' => DB::table($table)->orderBy($key[0])->lockForUpdate()->get()->keyBy($key[0])->map(fn ($r) => (array) $r)->all()];
        }
        return $snapshot;
    }

    public function archive(array $before, array $after, int $actor, string $label): ?int
    {
        $changes = [];
        $deleted = 0;
        $labels = [];
        foreach ($before as $table => $state) {
            foreach ($state['rows'] as $id => $row) {
                $current = $after[$table]['rows'][$id] ?? null;
                if ($row == $current) continue;
                $changes[$table][] = ['key' => $state['key'], 'id' => $id, 'before' => $row, 'after' => $current];
                if ($current === null) {
                    $deleted++;
                    $recordName = collect($row)->only(['project_name','supplier_name','item_name','manager_name','phase_name','unit_name','category_name','title','name','expense_description'])->first();
                    $labels[] = str($table)->replace('_tbl','')->replace('_',' ')->title().' — '.($recordName ?: 'Record '.$id);
                }
            }
        }
        if (!$deleted) return null;
        return DB::table('trash_entries')->insertGetId([
            'deleted_by' => $actor, 'label' => mb_substr(implode('; ', array_slice($labels, 0, 3)).($deleted > 3 ? ' (+'.($deleted-3).' related records)' : ''), 0, 255), 'payload' => Crypt::encrypt(['role' => strtolower((string) request()->user()?->role), 'tables' => $changes]),
            'deleted_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
    }

    public function restore(int $id, object $user): void
    {
        DB::transaction(function () use ($id, $user) {
            $entry = DB::table('trash_entries')->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry && (strtolower($user->role) === 'admin' || (int)$entry->deleted_by === (int)$user->id), 404);
            abort_if(now()->greaterThanOrEqualTo($entry->expires_at), 410, 'The 30-day recovery period has expired.');
            $payload = Crypt::decrypt($entry->payload);
            abort_if(strtolower($user->role) !== 'admin' && ($payload['role'] ?? strtolower($user->role)) !== strtolower($user->role), 403, 'Your current role cannot restore these records. Ask an administrator.');
            $changes = $payload['tables'] ?? $payload;
            foreach (self::TABLES as $table) {
                foreach ($changes[$table] ?? [] as $change) {
                    $current = DB::table($table)->where($change['key'], $change['id'])->lockForUpdate()->first();
                    $current = $current ? (array)$current : null;
                    if ($current != $change['after']) {
                        throw ValidationException::withMessages(['restore' => 'Cannot restore because a related record changed or its ID is already in use. No data was overwritten.']);
                    }
                    $row = $change['before'];
                    if ($table === 'users') {
                        $row['remember_token'] = null;
                        foreach (['first_login_otp','first_login_otp_expires_at','active_session_token','active_tab_token'] as $field) if (array_key_exists($field, $row)) $row[$field] = null;
                    }
                    if ($current === null) DB::table($table)->insert($row);
                    else DB::table($table)->where($change['key'], $change['id'])->update($row);
                }
            }
            app(AuditLogService::class)->recordOperation('UPDATE', 'Trash Bin', 'trash_entries', 'Restored deleted records from Trash Bin: '.$entry->label);
            DB::table('trash_entries')->where('id', $id)->delete();
        });
    }

    public function retainFile(?string $path): void
    {
        // DELETE requests retain referenced files for the same recovery period.
        if ($path && !request()->isMethod('DELETE')) Storage::disk('protected')->delete($path);
    }

    public function purgeExpired(): int
    {
        $count = 0;
        DB::table('trash_entries')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($entries) use (&$count) {
            foreach ($entries as $entry) {
                DB::transaction(function () use ($entry, &$count) {
                    $locked = DB::table('trash_entries')->where('id', $entry->id)->lockForUpdate()->first();
                    if (!$locked || now()->lessThan($locked->expires_at)) return;
                    $payload = Crypt::decrypt($locked->payload);
                    $changes = $payload['tables'] ?? $payload;
                    foreach ($changes as $table => $rows) foreach ($rows as $change) {
                        if ($change['after'] !== null) continue;
                        foreach (['proof_file_path','file_path'] as $field) {
                            $path = $change['before'][$field] ?? null;
                            if (!$path || str_contains($path, '..') || str_starts_with($path, '/')) continue;
                            $used = false;
                            foreach (self::TABLES as $active) {
                                if (Schema::hasColumn($active, $field) && DB::table($active)->where($field, $path)->exists()) { $used = true; break; }
                            }
                            if (!$used) Storage::disk('protected')->delete($path);
                        }
                    }
                    DB::table('trash_entries')->where('id', $entry->id)->delete();
                    $count++;
                });
            }
        });
        return $count;
    }
}
