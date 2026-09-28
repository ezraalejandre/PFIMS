<?php

namespace App\Services;

use App\Exceptions\ImportValidationException;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InventoryImportService
{
    private const ITEM_REQUIRED_HEADERS = ['item_name', 'category', 'supplier', 'unit', 'current_stock', 'reorder_level', 'opening_balance_date'];

    private const ITEM_ALLOWED_HEADERS = ['item_name', 'category', 'supplier', 'unit', 'current_stock', 'reorder_level', 'opening_balance_date'];

    private const TRANSACTION_REQUIRED_HEADERS = ['item_name', 'project_name', 'transaction_type', 'quantity', 'bar_code', 'transaction_date'];

    private const TRANSACTION_ALLOWED_HEADERS = ['item_name', 'project_name', 'transaction_type', 'quantity', 'bar_code', 'transaction_date', 'stock_in_reason', 'total_purchase_amount'];

    public function __construct(
        private TabularImportReader $reader,
        private InventoryCostAllocator $costAllocator,
        private InventoryPurchaseService $purchaseService,
    ) {}

    public function import(UploadedFile $file, string $type): array
    {
        return match ($type) {
            'items' => $this->importItems($this->reader->read($file)),
            'transactions' => $this->importTransactions($this->reader->read($file)),
            default => throw new ImportValidationException('Invalid inventory import type.'),
        };
    }

    private function importItems(array $sheet): array
    {
        $missing = array_diff(self::ITEM_REQUIRED_HEADERS, $sheet['headers']);
        $unexpected = array_diff($sheet['headers'], self::ITEM_ALLOWED_HEADERS);
        if ($missing !== [] || $unexpected !== []) {
            throw new ImportValidationException($this->headerMessage('inventory-item', $missing, $unexpected));
        }

        $categories = $this->lookupGroups('inventory_category_tbl', 'inventory_category_id', 'inventory_category_name');
        $suppliers = $this->lookupGroups('supplier_tbl', 'supplier_id', 'supplier_name');
        $units = $this->lookupGroups('unit_tbl', 'unit_id', 'unit_name');
        $prepared = [];
        $errors = [];
        $fileKeys = [];

        foreach ($sheet['rows'] as $row) {
            $values = $row['values'];
            $values['opening_balance_date'] = $this->normalizeDate($values['opening_balance_date']);
            $validator = Validator::make($values, [
                'item_name' => ['required', 'string', 'max:100'],
                'category' => ['required', 'string', 'max:100'],
                'supplier' => ['required', 'string', 'max:100'],
                'unit' => ['required', 'string', 'max:100'],
                'current_stock' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
                'reorder_level' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
                'opening_balance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            ]);
            if ($validator->fails()) {
                $this->appendValidationErrors($errors, $row['row'], $validator->errors()->toArray());

                continue;
            }
            $values = $validator->validated();

            $categoryId = $this->resolveLookup($categories, $values['category'], $row['row'], 'category', $errors);
            $supplierId = $this->resolveLookup($suppliers, $values['supplier'], $row['row'], 'supplier', $errors);
            $unitId = $this->resolveLookup($units, $values['unit'], $row['row'], 'unit', $errors);
            if (! $categoryId || ! $supplierId || ! $unitId) {
                continue;
            }

            $record = [
                'item_name' => trim($values['item_name']),
                'inventory_category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'unit_id' => $unitId,
                'current_stock' => round((float) $values['current_stock'], 2),
                'reorder_level' => round((float) $values['reorder_level'], 2),
                'opening_balance_date' => $values['opening_balance_date'],
            ];
            $key = implode('|', [$this->key($record['item_name']), $categoryId, $supplierId, $unitId, number_format($record['current_stock'], 2, '.', ''), number_format($record['reorder_level'], 2, '.', ''), $record['opening_balance_date']]);
            if (isset($fileKeys[$key])) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'Duplicates row '.$fileKeys[$key].' in this file.');

                continue;
            }
            $fileKeys[$key] = $row['row'];
            if ($this->duplicateItemQuery($record)->exists()) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'This inventory item already exists in PFIMS.');

                continue;
            }
            $prepared[] = ['row' => $row['row'], 'data' => $record];
        }

        $this->throwWhenErrors($errors);

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $row) {
                if ($this->duplicateItemQuery($row['data'])->lockForUpdate()->exists()) {
                    throw new ImportValidationException('No rows were imported because a duplicate was created while the file was being checked.', [
                        $this->rowError($row['row'], 'duplicate', 'This inventory item now already exists in PFIMS.'),
                    ]);
                }
                $stock = $row['data']['current_stock'];
                $itemData = $row['data'];
                $openingBalanceDate = $itemData['opening_balance_date'];
                unset($itemData['opening_balance_date']);
                $itemData['current_stock'] = 0;
                $itemId = DB::table('inventory_item_tbl')->insertGetId($itemData);
                if ($stock > 0) {
                    DB::table('inventory_transaction_tbl')->insert([
                        'item_id' => $itemId,
                        'project_id' => null,
                        'transaction_type' => 'IN',
                        'movement_reason' => 'opening_balance',
                        'recorded_at' => now(),
                        'quantity' => $stock,
                        'bar_code' => null,
                        'transaction_date' => $openingBalanceDate,
                        'proof_file_path' => null,
                        'proof_file_name' => 'Imported opening balance',
                    ]);
                    DB::table('inventory_item_tbl')->where('item_id', $itemId)->update(['current_stock' => $stock]);
                }
            }
        });

        return ['imported' => count($prepared), 'type' => 'inventory_items'];
    }

    private function importTransactions(array $sheet): array
    {
        $missing = array_diff(self::TRANSACTION_REQUIRED_HEADERS, $sheet['headers']);
        $unexpected = array_diff($sheet['headers'], self::TRANSACTION_ALLOWED_HEADERS);
        if ($missing !== [] || $unexpected !== []) {
            throw new ImportValidationException($this->headerMessage('inventory-transaction', $missing, $unexpected));
        }
        $hasReason = in_array('stock_in_reason', $sheet['headers'], true);
        $hasAmount = in_array('total_purchase_amount', $sheet['headers'], true);
        if ($hasReason !== $hasAmount) {
            throw new ImportValidationException('Include both stock_in_reason and total_purchase_amount, or use the legacy six-column format.');
        }
        $pricedFormat = $hasReason && $hasAmount;

        $items = DB::table('inventory_item_tbl')->select('item_id', 'item_name', 'current_stock')->get()
            ->groupBy(fn ($row) => $this->key($row->item_name));
        $projects = DB::table('project_tbl')->select('project_id', 'project_name')->get()
            ->groupBy(fn ($row) => $this->key($row->project_name));
        $prepared = [];
        $errors = [];
        $fileKeys = [];
        $simulatedStock = [];
        $unpricedCount = 0;

        foreach ($sheet['rows'] as $row) {
            $values = $row['values'] + ['project_name' => null, 'bar_code' => null];
            $values['transaction_type'] = strtoupper(trim((string) ($values['transaction_type'] ?? '')));
            $values['transaction_date'] = $this->normalizeDate($values['transaction_date'] ?? null);
            if ($pricedFormat) {
                $values['stock_in_reason'] = str_replace([' ', '-'], '_', strtolower(trim((string) ($values['stock_in_reason'] ?? ''))));
                $values['total_purchase_amount'] = trim((string) ($values['total_purchase_amount'] ?? ''));
            }
            $validator = Validator::make($values, [
                'item_name' => ['required', 'string', 'max:100'],
                'project_name' => ['nullable', 'string', 'max:100'],
                'transaction_type' => ['required', 'in:IN,OUT'],
                'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
                'bar_code' => ['required', 'regex:/\A[0-9]{1,64}\z/'],
                'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'stock_in_reason' => $pricedFormat ? ['required', 'in:purchase,return,adjustment,opening_balance,n/a'] : ['prohibited'],
                'total_purchase_amount' => $pricedFormat ? ['required'] : ['prohibited'],
            ]);
            if ($validator->fails()) {
                $this->appendValidationErrors($errors, $row['row'], $validator->errors()->toArray());

                continue;
            }
            $data = $validator->validated();

            $projectName = trim((string) ($data['project_name'] ?? ''));
            $forStorage = strcasecmp($projectName, 'For Storage') === 0;
            if ($data['transaction_type'] === 'OUT' && ($projectName === '' || $forStorage)) {
                $errors[] = $this->rowError($row['row'], 'project_name', 'Project name is required for OUT inventory transactions.');

                continue;
            }
            if ($data['transaction_type'] === 'IN' && $projectName !== '' && ! $forStorage) {
                $errors[] = $this->rowError($row['row'], 'project_name', 'Stock-in is for storage; use For Storage or leave the project blank.');

                continue;
            }
            $reason = $data['transaction_type'] === 'IN'
                ? ($pricedFormat ? $data['stock_in_reason'] : 'legacy_unpriced') : null;
            $purchaseAmount = null;
            if ($pricedFormat) {
                $rawAmount = $data['total_purchase_amount'];
                if ($data['transaction_type'] === 'IN' && $reason === 'purchase') {
                    if (! is_numeric($rawAmount) || (float) $rawAmount < 0.01 || (float) $rawAmount > 9999999999.99) {
                        $errors[] = $this->rowError($row['row'], 'total_purchase_amount', 'Purchase IN requires a positive total amount within the Finance limit.');
                        continue;
                    }
                    $purchaseAmount = round((float) $rawAmount, 2);
                } elseif ($rawAmount !== 'N/A' && $rawAmount !== 'n/a') {
                    $errors[] = $this->rowError($row['row'], 'total_purchase_amount', 'Use N/A when this row is not a purchase IN.');
                    continue;
                }
                if (($data['transaction_type'] === 'OUT' && $data['stock_in_reason'] !== 'n/a')
                    || ($data['transaction_type'] === 'IN' && $reason === 'n/a')) {
                    $errors[] = $this->rowError($row['row'], 'stock_in_reason', 'Choose a stock-in reason for IN, or N/A for OUT.');
                    continue;
                }
            }

            $itemMatches = $items->get($this->key($data['item_name']), collect());
            if ($itemMatches->count() !== 1) {
                $errors[] = $this->rowError($row['row'], 'item_name', $itemMatches->isEmpty() ? 'Inventory item not found.' : 'Inventory item name is ambiguous.');

                continue;
            }
            $item = $itemMatches->first();
            $projectId = null;
            if ($data['transaction_type'] === 'OUT') {
                $projectMatches = $projects->get($this->key($data['project_name']), collect());
                if ($projectMatches->count() !== 1) {
                    $errors[] = $this->rowError($row['row'], 'project_name', $projectMatches->isEmpty() ? 'Project not found.' : 'Project name is ambiguous.');

                    continue;
                }
                $projectId = (int) $projectMatches->first()->project_id;
            }

            $record = [
                'item_id' => (int) $item->item_id,
                'project_id' => $projectId,
                'transaction_type' => $data['transaction_type'],
                'quantity' => round((float) $data['quantity'], 2),
                'bar_code' => (string) $data['bar_code'],
                'transaction_date' => $data['transaction_date'],
                'movement_reason' => $reason,
            ];
            $key = $this->transactionKey($record, $purchaseAmount);
            if (isset($fileKeys[$key])) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'Duplicates row '.$fileKeys[$key].' in this file.');

                continue;
            }
            $fileKeys[$key] = $row['row'];
            if ($this->duplicateTransactionQuery($record, $purchaseAmount)->exists()) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'This inventory transaction already exists in PFIMS.');

                continue;
            }

            $stock = $simulatedStock[$record['item_id']] ?? (float) $item->current_stock;
            $stock += $record['transaction_type'] === 'IN' ? $record['quantity'] : -$record['quantity'];
            if ($stock < 0) {
                $errors[] = $this->rowError($row['row'], 'quantity', 'This OUT transaction exceeds the available stock at this point in the file.');

                continue;
            }
            $simulatedStock[$record['item_id']] = $stock;
            $prepared[] = ['row' => $row['row'], 'data' => $record, 'purchase_amount' => $purchaseAmount];
            if ($reason === 'legacy_unpriced') {
                $unpricedCount++;
            }
        }

        $this->throwWhenErrors($errors);

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $row) {
                $item = DB::table('inventory_item_tbl')->where('item_id', $row['data']['item_id'])->lockForUpdate()->first();
                if (! $item) {
                    throw new ImportValidationException('No rows were imported because an inventory item was removed during validation.', [
                        $this->rowError($row['row'], 'item_name', 'Inventory item no longer exists.'),
                    ]);
                }
                if ($this->duplicateTransactionQuery($row['data'], $row['purchase_amount'])->lockForUpdate()->exists()) {
                    throw new ImportValidationException('No rows were imported because a duplicate was created while the file was being checked.', [
                        $this->rowError($row['row'], 'duplicate', 'This inventory transaction now already exists in PFIMS.'),
                    ]);
                }
                $newStock = (float) $item->current_stock + ($row['data']['transaction_type'] === 'IN' ? $row['data']['quantity'] : -$row['data']['quantity']);
                if ($newStock < 0) {
                    throw new ImportValidationException('No rows were imported because stock changed while the file was being checked.', [
                        $this->rowError($row['row'], 'quantity', 'Available stock is now insufficient.'),
                    ]);
                }
                $transactionId = DB::table('inventory_transaction_tbl')->insertGetId($row['data'] + [
                    'recorded_at' => now(),
                    'proof_file_path' => null,
                    'proof_file_name' => 'Imported data',
                ]);
                DB::table('inventory_item_tbl')->where('item_id', $item->item_id)->update(['current_stock' => $newStock]);
                if ($row['data']['movement_reason'] === 'purchase') {
                    try {
                        $this->purchaseService->createLinkedExpense((int) $transactionId, $row['purchase_amount']);
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                        throw new ImportValidationException('No rows were imported because a purchase could not be recorded.', [
                            $this->rowError($row['row'], 'total_purchase_amount', $e->getMessage()),
                        ]);
                    }
                }
                if ($row['data']['transaction_type'] === 'OUT') {
                    try {
                        $this->costAllocator->allocate((int) $transactionId);
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                        throw new ImportValidationException('No rows were imported because a stock-out could not be costed.', [
                            $this->rowError($row['row'], 'quantity', $e->getMessage()),
                        ]);
                    }
                }
            }
        });

        return [
            'imported' => count($prepared), 'type' => 'inventory_transactions',
            'unpriced_stock_in_count' => $unpricedCount,
            'project_ids' => collect($prepared)->pluck('data.project_id')->filter()
                ->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ];
    }

    private function lookupGroups(string $table, string $id, string $name)
    {
        return DB::table($table)
            ->selectRaw("{$id} as lookup_id, {$name} as lookup_name")
            ->get()
            ->groupBy(fn ($row) => $this->key($row->lookup_name));
    }

    private function resolveLookup($groups, string $value, int $row, string $field, array &$errors): ?int
    {
        $matches = $groups->get($this->key($value), collect());
        if ($matches->count() !== 1) {
            $errors[] = $this->rowError($row, $field, $matches->isEmpty() ? ucfirst($field).' not found in Settings > Configurations.' : ucfirst($field).' is ambiguous.');

            return null;
        }

        return (int) $matches->first()->lookup_id;
    }

    private function duplicateItemQuery(array $record)
    {
        $query = DB::table('inventory_item_tbl')
            ->whereRaw('LOWER(TRIM(item_name)) = ?', [$this->key($record['item_name'])])
            ->where('inventory_category_id', $record['inventory_category_id'])
            ->where('supplier_id', $record['supplier_id'])
            ->where('unit_id', $record['unit_id'])
            ->where('current_stock', $record['current_stock'])
            ->where('reorder_level', $record['reorder_level']);

        if ((float) $record['current_stock'] <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($opening) use ($record) {
            $opening->selectRaw('1')
                ->from('inventory_transaction_tbl as opening_transaction')
                ->whereColumn('opening_transaction.item_id', 'inventory_item_tbl.item_id')
                ->where('opening_transaction.transaction_type', 'IN')
                ->where('opening_transaction.quantity', $record['current_stock'])
                ->whereDate('opening_transaction.transaction_date', $record['opening_balance_date'])
                ->whereNull('opening_transaction.project_id')
                ->whereNull('opening_transaction.bar_code')
                ->where('opening_transaction.proof_file_name', 'Imported opening balance');
        });
    }

    private function duplicateTransactionQuery(array $record, $purchaseAmount = null)
    {
        $query = DB::table('inventory_transaction_tbl')
            ->where('item_id', $record['item_id'])
            ->where('transaction_type', $record['transaction_type'])
            ->where('quantity', $record['quantity'])
            ->whereDate('transaction_date', $record['transaction_date']);
        $query = $record['project_id'] === null ? $query->whereNull('project_id') : $query->where('project_id', $record['project_id']);
        $query = $record['bar_code'] === null ? $query->whereNull('bar_code') : $query->where('bar_code', $record['bar_code']);
        if ($record['movement_reason'] === 'legacy_unpriced') {
            $query->where(function ($reason) {
                $reason->whereNull('movement_reason')->orWhere('movement_reason', 'legacy_unpriced');
            });
        } elseif ($record['movement_reason'] === null) {
            $query->whereNull('movement_reason');
        } else {
            $query->where('movement_reason', $record['movement_reason']);
        }
        if ($record['movement_reason'] === 'purchase') {
            $query->whereExists(function ($expense) use ($purchaseAmount) {
                $expense->selectRaw('1')->from('fin_expense_tbl')
                    ->whereColumn('fin_expense_tbl.inventory_transaction_id', 'inventory_transaction_tbl.inventory_transaction_id')
                    ->where('fin_expense_tbl.amount', $purchaseAmount);
            });
        }

        return $query;
    }

    private function transactionKey(array $record, $purchaseAmount = null): string
    {
        return implode('|', [$record['item_id'], $record['project_id'] ?? '', $record['transaction_type'], number_format($record['quantity'], 2, '.', ''), $record['bar_code'] ?? '', $record['transaction_date'], $record['movement_reason'] ?? '', $purchaseAmount === null ? '' : number_format($purchaseAmount, 2, '.', '')]);
    }

    private function normalizeDate(mixed $value): mixed
    {
        if (is_numeric($value) && (float) $value >= 1 && (float) $value < 100000) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }
        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, trim($value));
                if ($date !== false && $date->format($format) === trim($value)) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return $value;
    }

    private function headerMessage(string $type, array $missing, array $unexpected): string
    {
        $parts = [];
        if ($missing !== []) {
            $parts[] = 'Missing: '.implode(', ', $missing).'.';
        }
        if ($unexpected !== []) {
            $parts[] = 'Unexpected: '.implode(', ', $unexpected).'.';
        }

        return 'Invalid '.$type.' headers. '.implode(' ', $parts);
    }

    private function throwWhenErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new ImportValidationException('No rows were imported. Correct the listed row errors and upload the file again.', $errors);
        }
    }

    private function appendValidationErrors(array &$errors, int $row, array $messages): void
    {
        foreach ($messages as $field => $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $errors[] = $this->rowError($row, $field, $message);
            }
        }
    }

    private function rowError(int $row, string $field, string $message): array
    {
        return compact('row', 'field', 'message');
    }

    private function key(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }
}
