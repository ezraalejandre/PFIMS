<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportValidationException;
use App\Services\FinanceImportService;
use App\Services\InventoryImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    public function finance(Request $request, FinanceImportService $service): JsonResponse
    {
        $this->authorizeRole($request, ['admin', 'accounting']);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        return $this->runImport(fn () => $service->import($validated['file']));
    }

    public function inventory(Request $request, InventoryImportService $service): JsonResponse
    {
        $this->authorizeRole($request, ['admin', 'operations']);
        $validated = $request->validate([
            'type' => ['required', 'in:items,transactions'],
            'file' => ['required', 'file', 'max:5120'],
        ]);

        return $this->runImport(fn () => $service->import($validated['file'], $validated['type']));
    }

    public function template(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['finance-expenses', 'inventory-items', 'inventory-transactions'], true), 404);
        $this->authorizeRole($request, $type === 'finance-expenses' ? ['admin', 'accounting'] : ['admin', 'operations']);

        $rows = match ($type) {
            'finance-expenses' => [
                ['project_name', 'category_code', 'project_cost_component', 'expense_description', 'amount', 'expense_date', 'remarks'],
                [
                    (string) (DB::table('project_tbl')->orderBy('project_name')->value('project_name') ?? ''),
                    (string) (DB::table('fin_expense_category_tbl')->where('is_active', true)->orderBy('category_name')->value('category_code') ?? 'CATEGORY_CODE'),
                    'material',
                    'Replace with expense description', '0.01', now()->toDateString(), 'Optional note',
                ],
            ],
            'inventory-items' => [
                ['item_name', 'category', 'supplier', 'unit', 'current_stock', 'reorder_level', 'opening_balance_date'],
                [
                    'Replace with a new item name',
                    (string) (DB::table('inventory_category_tbl')->orderBy('inventory_category_name')->value('inventory_category_name') ?? 'CATEGORY_NAME'),
                    (string) (DB::table('supplier_tbl')->orderBy('supplier_name')->value('supplier_name') ?? 'SUPPLIER_NAME'),
                    (string) (DB::table('unit_tbl')->orderBy('unit_name')->value('unit_name') ?? 'UNIT_NAME'),
                    '0', '0', now()->toDateString(),
                ],
            ],
            'inventory-transactions' => [
                ['item_name', 'project_name', 'transaction_type', 'quantity', 'bar_code', 'transaction_date'],
                [
                    (string) (DB::table('inventory_item_tbl')->orderBy('item_name')->value('item_name') ?? 'ITEM_NAME'),
                    (string) (DB::table('project_tbl')->orderBy('project_name')->value('project_name') ?? ''),
                    'IN', '1', '', now()->toDateString(),
                ],
            ],
        };

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $type.'-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function runImport(callable $callback): JsonResponse
    {
        try {
            $result = $callback();

            return response()->json([
                'success' => true,
                'message' => $result['imported'].' row(s) imported successfully.',
                'data' => $result,
            ], 201);
        } catch (ImportValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->rowErrors,
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'The import could not be completed. No rows were imported.',
                'errors' => [],
            ], 500);
        }
    }

    private function authorizeRole(Request $request, array $allowed): void
    {
        $role = strtolower((string) $request->user()?->role);
        abort_unless(in_array($role, $allowed, true), 403, 'You are not authorized to import this module.');
    }
}
