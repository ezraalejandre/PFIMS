<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportValidationException;
use App\Services\AutomaticModelRetraining;
use App\Services\AuditLogService;
use App\Services\FinanceImportService;
use App\Services\InventoryImportService;
use App\Services\ProjectImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    public function __construct(private AutomaticModelRetraining $modelRetraining, private AuditLogService $audit) {}

    public function finance(Request $request, FinanceImportService $service): JsonResponse
    {
        $this->authorizeRole($request, ['admin', 'accounting']);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        return $this->runImport(fn () => $service->import($validated['file']), 'Expenses', 'fin_expense_tbl');
    }

    public function inventory(Request $request, InventoryImportService $service): JsonResponse
    {
        $this->authorizeRole($request, ['admin', 'operations']);
        $validated = $request->validate([
            'type' => ['required', 'in:items,transactions'],
            'file' => ['required', 'file', 'max:5120'],
        ]);

        return $this->runImport(
            fn () => $service->import($validated['file'], $validated['type']),
            $validated['type'] === 'items' ? 'Inventory' : 'Inventory Transactions',
            $validated['type'] === 'items' ? 'inventory_item_tbl' : 'inventory_transaction_tbl'
        );
    }

    public function projects(Request $request, ProjectImportService $service): JsonResponse
    {
        $this->authorizeRole($request, ['admin', 'operations']);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        return $this->runImport(fn () => $service->import($validated['file']), 'Projects', 'project_tbl');
    }

    public function template(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['projects', 'finance-expenses', 'inventory-items', 'inventory-transactions'], true), 404);
        $this->authorizeRole($request, $type === 'finance-expenses' ? ['admin', 'accounting'] : ['admin', 'operations']);

        $rows = match ($type) {
            'projects' => [
                ['project_name', 'client_name', 'project_manager', 'start_date', 'estimated_end_date', 'actual_end_date', 'worker_count', 'phase', 'status', 'budget'],
                [
                    'Replace with project name', 'Replace with client name', 'Replace with project manager',
                    'YYYY-MM-DD', 'YYYY-MM-DD', '', '1',
                    (string) (DB::table('project_phase_tbl')->orderBy('stage_order')->value('phase_name') ?? 'PHASE_NAME'),
                    'Pending', '0',
                ],
            ],
            'finance-expenses' => [
                ['project_name', 'category_code', 'project_cost_component', 'expense_description', 'amount', 'expense_date', 'remarks'],
                [
                    (string) (DB::table('project_tbl')->orderBy('project_name')->value('project_name') ?? 'PROJECT_NAME'),
                    (string) (DB::table('fin_expense_category_tbl')->where('is_active', true)->orderBy('category_name')->value('category_code') ?? 'CATEGORY_CODE'),
                    'material',
                    'Replace with expense description', '0.01', 'YYYY-MM-DD', 'Optional note',
                ],
            ],
            'inventory-items' => [
                ['item_name', 'category', 'supplier', 'unit', 'current_stock', 'reorder_level', 'opening_balance_date'],
                [
                    'Replace with a new item name',
                    (string) (DB::table('inventory_category_tbl')->orderBy('inventory_category_name')->value('inventory_category_name') ?? 'CATEGORY_NAME'),
                    (string) (DB::table('supplier_tbl')->orderBy('supplier_name')->value('supplier_name') ?? 'SUPPLIER_NAME'),
                    (string) (DB::table('unit_tbl')->orderBy('unit_name')->value('unit_name') ?? 'UNIT_NAME'),
                    '0', '0', 'YYYY-MM-DD',
                ],
            ],
            'inventory-transactions' => [
                ['item_name', 'project_name', 'transaction_type', 'quantity', 'bar_code', 'transaction_date', 'stock_in_reason', 'total_purchase_amount'],
                [
                    (string) (DB::table('inventory_item_tbl')->orderBy('item_name')->value('item_name') ?? 'ITEM_NAME'),
                    'For Storage', 'IN', '1', '100001', 'YYYY-MM-DD', 'purchase', '100.00',
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

    private function runImport(callable $callback, string $module, string $table): JsonResponse
    {
        try {
            $result = $callback();
            $projectIds = array_values(array_filter(array_map('intval', $result['project_ids'] ?? [])));
            if ($projectIds !== []) {
                $this->modelRetraining->afterDataChange($projectIds);
            }
            if (($result['imported'] ?? 0) > 0) {
                $this->audit->recordOperation('IMPORT', $module, $table, 'Imported '.$result['imported'].' '.$module.' record(s)');
            }

            $message = $result['imported'].' row(s) imported successfully.';
            if (($result['unpriced_stock_in_count'] ?? 0) > 0) {
                $message .= ' '.$result['unpriced_stock_in_count'].' legacy stock-in row(s) remain unpriced and are flagged for review.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
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
