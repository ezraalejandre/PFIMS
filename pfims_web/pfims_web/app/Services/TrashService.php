<?php
namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// Group the trash actions.
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

    // Get the saved values for this point in time.
    public function snapshot(): array
    {
        $snapshot = [];
        // Process each table.
        foreach (self::TABLES as $table) {
            // Skip this item if the table check matches.
            if (!Schema::hasTable($table)) continue;
            $key = collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'] ?? [];
            // Stop if the number of key does not equal 1.
            if (count($key) !== 1) throw new \RuntimeException('Trash requires a single primary key: '.$table);
            $snapshot[$table] = ['key' => $key[0], 'rows' => DB::table($table)->orderBy($key[0])->lockForUpdate()->get()->keyBy($key[0])->map(fn ($r) => (array) $r)->all()];
        }
        return $snapshot;
    }

    // Get the archive.
    public function archive(array $before, array $after, int $actor, string $label): ?int
    {
        $changes = [];
        $deleted = 0;
        $labels = [];
        // Process each state.
        foreach ($before as $table => $state) {
            // Process each row.
            foreach ($state['rows'] as $id => $row) {
                $current = $after[$table]['rows'][$id] ?? null;
                // Skip this item if the row equals current.
                if ($row == $current) continue;
                $changes[$table][] = ['key' => $state['key'], 'id' => $id, 'before' => $row, 'after' => $current];
                // Run this step if the current is null.
                if ($current === null) {
                    $deleted++;
                    $recordName = collect($row)->only(['project_name','supplier_name','item_name','manager_name','phase_name','unit_name','category_name','title','name','expense_description'])->first();
                    $labels[] = str($table)->replace('_tbl','')->replace('_',' ')->title().' — '.($recordName ?: 'Record '.$id);
                }
            }
        }
        // Return early if the deleted is missing or false.
        if (!$deleted) return null;
        return DB::table('trash_entries')->insertGetId([
            'deleted_by' => $actor, 'label' => mb_substr(implode('; ', array_slice($labels, 0, 3)).($deleted > 3 ? ' (+'.($deleted-3).' related records)' : ''), 0, 255), 'payload' => Crypt::encrypt(['role' => strtolower((string) request()->user()?->role), 'tables' => $changes]),
            'deleted_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
    }

    // Get the restore.
    public function restore(int $id, object $user): void
    {
        // Run these database steps together. Undo changes if a step fails.
        DB::transaction(function () use ($id, $user) {
            $entry = DB::table('trash_entries')->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry && (strtolower($user->role) === 'admin' || (int)$entry->deleted_by === (int)$user->id), 404);
            abort_if(now()->greaterThanOrEqualTo($entry->expires_at), 410, 'The 30-day recovery period has expired.');
            $payload = Crypt::decrypt($entry->payload);
            abort_if(strtolower($user->role) !== 'admin' && ($payload['role'] ?? strtolower($user->role)) !== strtolower($user->role), 403, 'Your current role cannot restore these records. Ask an administrator.');
            $changes = $payload['tables'] ?? $payload;
            // Process each table.
            foreach (self::TABLES as $table) {
                // Process each change.
                foreach ($changes[$table] ?? [] as $change) {
                    $current = DB::table($table)->where($change['key'], $change['id'])->lockForUpdate()->first();
                    $current = $current ? (array)$current : null;
                    // Stop if the current does not equal change after.
                    if ($current != $change['after']) {
                        throw ValidationException::withMessages(['restore' => 'Cannot restore because a related record changed or its ID is already in use. No data was overwritten.']);
                    }
                    $row = $change['before'];
                    // Run this step if the table equals users.
                    if ($table === 'users') {
                        $row['remember_token'] = null;
                        // Process each field.
                        foreach (['first_login_otp','first_login_otp_expires_at','active_session_token','active_tab_token'] as $field) if (array_key_exists($field, $row)) $row[$field] = null;
                    }
                    // Run this step if the current is null.
                    if ($current === null) DB::table($table)->insert($row);
                    else DB::table($table)->where($change['key'], $change['id'])->update($row);
                }
            }
            app(AuditLogService::class)->recordOperation('UPDATE', 'Trash Bin', 'trash_entries', 'Restored deleted records from Trash Bin: '.$entry->label);
            DB::table('trash_entries')->where('id', $id)->delete();
        });
    }

    // Get the retain file.
    public function retainFile(?string $path): void
    {
        // DELETE requests retain referenced files for the same recovery period.
        if ($path && !request()->isMethod('DELETE')) Storage::disk('protected')->delete($path);
    }

    // Restore all.
    public function restoreAll(array $ids, object $user): int
    {
        // Run these database steps together. Undo changes if a step fails.
        return DB::transaction(function () use ($ids, $user) {
            $entries = $this->selectedEntries($ids, $user);
            // Reverse deletion order also reverses dependent row updates.
            foreach ($entries as $entry) $this->restore((int) $entry->id, $user);
            return $entries->count();
        });
    }

    // Get the empty.
    public function empty(array $ids, object $user): int
    {
        // Run these database steps together. Undo changes if a step fails.
        return DB::transaction(function () use ($ids, $user) {
            $entries = $this->selectedEntries($ids, $user);
            // Run this step if the role does not equal admin.
            if (strtolower($user->role) !== 'admin') foreach ($entries as $entry) {
                $payload = Crypt::decrypt($entry->payload);
                abort_if(($payload['role'] ?? strtolower($user->role)) !== strtolower($user->role), 403, 'Your current role cannot permanently delete these records. Ask an administrator.');
            }
            $count = $this->permanentlyDelete($entries);
            app(AuditLogService::class)->recordOperation('DELETE', 'Trash Bin', 'trash_entries', 'Permanently emptied '.$count.' deleted records from Trash Bin.');
            return $count;
        });
    }

    // Get the selected entries.
    private function selectedEntries(array $ids, object $user): \Illuminate\Support\Collection
    {
        $query = DB::table('trash_entries')->whereIn('id', $ids);
        // Run this step if the role does not equal admin.
        if (strtolower($user->role) !== 'admin') $query->where('deleted_by', $user->id);
        $entries = $query->orderByDesc('id')->lockForUpdate()->get();
        abort_unless($entries->count() === count($ids), 404, 'Trash Bin has changed or a record is unavailable. Please try again.');
        return $entries;
    }

    // Get the permanently delete.
    private function permanentlyDelete(\Illuminate\Support\Collection $entries): int
    {
        $paths = [];
        // Process each entry.
        foreach ($entries as $entry) {
            $payload = Crypt::decrypt($entry->payload);
            // Process each rows.
            foreach (($payload['tables'] ?? $payload) as $rows) foreach ($rows as $change) {
                // Skip this item if the change after is not null.
                if ($change['after'] !== null) continue;
                // Process each field.
                foreach (['proof_file_path', 'file_path'] as $field) {
                    $path = $change['before'][$field] ?? null;
                    // Run this step if the value check matches.
                    if (is_string($path) && $path !== '' && !str_contains($path, '..') && !str_contains($path, '\\') && !str_starts_with($path, '/')) $paths[$path] = $path;
                }
            }
        }
        DB::table('trash_entries')->whereIn('id', $entries->pluck('id'))->delete();
        DB::afterCommit(function () use ($paths) {
            // Process each path.
            foreach ($paths as $path) {
                // Try this action and handle any errors below.
                try {
                    // Run this step if the path check matches.
                    if (!$this->fileIsRetained($path)) Storage::disk('protected')->delete($path);
                } catch (\Throwable $error) {
                    \Illuminate\Support\Facades\Log::warning('Trash attachment cleanup failed; file retained.', ['path' => $path, 'error' => $error->getMessage()]);
                }
            }
        });
        return $entries->count();
    }

    // Get the file is retained.
    private function fileIsRetained(string $path): bool
    {
        // Process each field.
        foreach (['proof_file_path', 'file_path'] as $field) foreach (self::TABLES as $table) {
            // Return early if the table and field checks match.
            if (Schema::hasColumn($table, $field) && DB::table($table)->where($field, $path)->exists()) return true;
        }
        // Another user's recoverable record may share this attachment.
        foreach (DB::table('trash_entries')->select('payload')->cursor() as $entry) {
            $payload = Crypt::decrypt($entry->payload);
            // Process each rows.
            foreach (($payload['tables'] ?? $payload) as $rows) foreach ($rows as $change) {
                // Return early if the change before and path checks match.
                if (($change['before']['proof_file_path'] ?? null) === $path || ($change['before']['file_path'] ?? null) === $path) return true;
            }
        }
        return false;
    }

    // Get the purge expired.
    public function purgeExpired(): int
    {
        $count = 0;
        // Group these checks when finding matching records.
        DB::table('trash_entries')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($entries) use (&$count) {
            // Process each entry.
            foreach ($entries as $entry) {
                // Run these database steps together. Undo changes if a step fails.
                DB::transaction(function () use ($entry, &$count) {
                    $locked = DB::table('trash_entries')->where('id', $entry->id)->lockForUpdate()->first();
                    // Run this step if the locked and expires at checks match.
                    if (!$locked || now()->lessThan($locked->expires_at)) return;
                    $this->permanentlyDelete(collect([$locked]));
                    $count++;
                });
            }
        });
        return $count;
    }
}
