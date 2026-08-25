<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const DATASETS = [
        'project' => [
            'title' => 'Project', 'type' => 'project', 'roles' => ['admin', 'operations'],
            'columns' => [
                'project_id' => 'Project ID', 'project_name' => 'Project', 'client_name' => 'Client',
                'project_manager' => 'Project Manager', 'phase' => 'Phase', 'status' => 'Status',
                'start_date' => 'Start Date', 'estimated_end_date' => 'Estimated End',
                'completion_percentage' => 'Completion (%)', 'worker_count' => 'Workers',
                'budget_amount' => 'Budget', 'actual_amount' => 'Actual Cost', 'variance' => 'Budget Variance',
            ],
            'filters' => ['search', 'project_id', 'status', 'start_date', 'end_date'],
        ],
        'finance' => [
            'title' => 'Finance', 'type' => 'finance', 'roles' => ['admin', 'accounting'],
            'columns' => [
                'fin_expense_id' => 'Expense ID', 'expense_date' => 'Expense Date',
                'project_name' => 'Project / Cost Center', 'category_name' => 'Category',
                'classification' => 'Classification', 'expense_description' => 'Description',
                'amount' => 'Amount', 'remarks' => 'Remarks',
            ],
            'filters' => ['search', 'project_id', 'classification', 'start_date', 'end_date'],
        ],
        'budget' => [
            'title' => 'Budget', 'type' => 'budget', 'roles' => ['admin', 'accounting'],
            'columns' => [
                'budget_id' => 'Budget ID', 'project_name' => 'Project', 'client_name' => 'Client',
                'status' => 'Project Status', 'budget_amount' => 'Budget', 'actual_amount' => 'Actual Cost',
                'remaining_amount' => 'Remaining', 'utilization_percentage' => 'Utilization (%)',
            ],
            'filters' => ['search', 'project_id', 'status', 'start_date', 'end_date'],
        ],
        'inventory' => [
            'title' => 'Inventory', 'type' => 'inventory', 'roles' => ['admin', 'operations'],
            'columns' => [
                'item_id' => 'Item ID', 'item_name' => 'Item', 'category_name' => 'Category',
                'supplier_name' => 'Supplier', 'unit_name' => 'Unit', 'current_stock' => 'Current Stock',
                'reorder_level' => 'Reorder Level', 'stock_status' => 'Stock Status',
                'last_transaction_date' => 'Last Movement',
            ],
            'filters' => ['search', 'category_id', 'supplier_id', 'stock_status'],
        ],
        'supplier' => [
            'title' => 'Supplier', 'type' => 'supplier', 'roles' => ['admin', 'operations'],
            'columns' => [
                'supplier_id' => 'Supplier ID', 'supplier_name' => 'Supplier', 'address' => 'Address',
                'contact_number' => 'Contact Number', 'item_count' => 'Items Supplied',
                'total_stock' => 'Total Stock', 'low_stock_items' => 'Low-stock Items',
                'last_delivery_date' => 'Last Delivery',
            ],
            'filters' => ['search', 'supplier_id'],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => ['nullable', Rule::in(array_keys(self::DATASETS))],
            'search' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $role = $this->role();
        $query = Report::query()->generated()->forRole($role);
        if (! empty($validated['dataset'])) {
            $this->authorizeDataset($validated['dataset']);
            $query->where('dataset_key', $validated['dataset']);
        }
        if (! empty($validated['search'])) {
            $search = $this->escapeLike($validated['search']);
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('report_id', 'like', "%{$search}%")
                    ->orWhere('uploaded_by', 'like', "%{$search}%");
            });
        }
        if (! empty($validated['start_date'])) {
            $query->whereDate('generated_at', '>=', $validated['start_date']);
        }
        if (! empty($validated['end_date'])) {
            $query->whereDate('generated_at', '<=', $validated['end_date']);
        }

        return response()->json($query->latest('generated_at')->paginate((int) ($validated['per_page'] ?? 20)));
    }

    public function catalog(): JsonResponse
    {
        $role = $this->role();
        $datasets = collect(self::DATASETS)
            ->filter(fn (array $definition) => in_array($role, $definition['roles'], true))
            ->map(fn (array $definition, string $key) => [
                'key' => $key, 'title' => $definition['title'], 'type' => $definition['type'],
                'columns' => $definition['columns'], 'filters' => $definition['filters'],
            ])->values();

        return response()->json([
            'datasets' => $datasets,
            'options' => [
                'projects' => DB::table('project_tbl')->orderBy('project_name')->get(['project_id as value', 'project_name as label']),
                'statuses' => DB::table('project_tbl')->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
                'categories' => DB::table('inventory_category_tbl')->orderBy('inventory_category_name')->get(['inventory_category_id as value', 'inventory_category_name as label']),
                'suppliers' => DB::table('supplier_tbl')->orderBy('supplier_name')->get(['supplier_id as value', 'supplier_name as label']),
                'classifications' => ['direct', 'admin'],
                'stock_statuses' => ['Out of Stock', 'Reorder Needed', 'Sufficient'],
                'formats' => [['value' => 'csv', 'label' => 'CSV']],
                'sections' => [
                    ['value' => 'summary', 'label' => 'Report and filter summary'],
                    ['value' => 'kpis', 'label' => 'KPI summary'],
                    ['value' => 'chart', 'label' => 'Chart data'],
                    ['value' => 'data', 'label' => 'Detailed rows'],
                ],
            ],
        ]);
    }

    public function data(Request $request, string $dataset): JsonResponse
    {
        $definition = $this->authorizeDataset($dataset);
        $pagination = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);
        $filters = $this->validatedFilters($request);
        $rows = $this->datasetRows($dataset, $filters);
        $total = $rows->count();
        $perPage = (int) ($pagination['per_page'] ?? 25);
        $lastPage = max((int) ceil($total / $perPage), 1);
        $currentPage = min((int) ($pagination['page'] ?? 1), $lastPage);
        $pageRows = $rows->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $from = $total === 0 ? null : (($currentPage - 1) * $perPage) + 1;
        $to = $total === 0 ? null : min($currentPage * $perPage, $total);

        return response()->json([
            'dataset' => $dataset, 'title' => $definition['title'], 'columns' => $definition['columns'],
            'rows' => $pageRows, 'total_rows' => $total,
            'truncated' => $total > $perPage, 'kpis' => $this->datasetKpis($dataset, $rows),
            'chart' => $this->datasetChart($dataset, $rows), 'filters' => $filters,
            'pagination' => [
                'current_page' => $currentPage, 'last_page' => $lastPage, 'per_page' => $perPage,
                'from' => $from, 'to' => $to, 'total' => $total,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'dataset' => ['required', Rule::in(array_keys(self::DATASETS))],
            'title' => 'required|string|min:3|max:120',
            'format' => ['required', Rule::in(['csv'])],
            'columns' => 'required|array|min:1', 'columns.*' => 'required|string|distinct|max:60',
            'sections' => 'required|array|min:1',
            'sections.*' => ['required', 'string', 'distinct', Rule::in(['summary', 'kpis', 'chart', 'data'])],
            'filters' => 'nullable|array',
        ]);

        $definition = $this->authorizeDataset($validated['dataset']);
        if (array_diff($validated['columns'], array_keys($definition['columns'])) !== []) {
            throw ValidationException::withMessages(['columns' => 'One or more selected columns are unavailable.']);
        }

        $filterRequest = Request::create('/', 'GET', $validated['filters'] ?? []);
        $filters = $this->validatedFilters($filterRequest);
        $rows = $this->datasetRows($validated['dataset'], $filters);
        $kpis = $this->datasetKpis($validated['dataset'], $rows);
        $chart = $this->datasetChart($validated['dataset'], $rows);
        $role = $this->role();
        $fileName = (Str::slug($validated['title']) ?: $validated['dataset']).'-'.now()->format('Ymd-His').'-'.strtolower(Str::random(4)).'.csv';
        $filePath = "reports/exports/{$role}/{$fileName}";
        $csv = $this->buildCsv($validated['title'], $definition, $validated['columns'], $validated['sections'], $filters, $rows, $kpis, $chart);
        Storage::disk('public')->put($filePath, $csv);

        try {
            $report = Report::create([
                'report_id' => Report::generateReportId(), 'title' => $validated['title'],
                'type' => $definition['type'], 'role' => $role,
                'description' => 'System-generated '.$definition['title'].' report',
                'file_name' => $fileName, 'file_path' => $filePath,
                'file_size' => Storage::disk('public')->size($filePath),
                'date_uploaded' => today()->toDateString(), 'uploaded_by' => Auth::user()->name,
                'status' => 'Completed', 'generation_method' => 'system_export',
                'dataset_key' => $validated['dataset'], 'export_format' => 'csv',
                'row_count' => $rows->count(), 'selected_columns' => $validated['columns'],
                'filters_applied' => $filters, 'export_options' => ['sections' => $validated['sections']],
                'generated_at' => now(), 'user_id' => Auth::id(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($filePath);
            throw $exception;
        }

        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');

        return $publicDisk->download($report->file_path, $report->file_name, ['X-Report-Id' => $report->report_id]);
    }

    public function download(string $id): StreamedResponse|JsonResponse
    {
        $report = Report::query()->generated()->where('report_id', $id)->firstOrFail();
        $this->authorizeReport($report);
        if (! Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'The exported file is no longer available.'], 404);
        }

        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');

        return $publicDisk->download($report->file_path, $report->file_name);
    }

    public function destroy(string $id): JsonResponse
    {
        $report = Report::query()->generated()->where('report_id', $id)->firstOrFail();
        $this->authorizeReport($report, true);
        Storage::disk('public')->delete($report->file_path);
        $report->delete();

        return response()->json(['message' => 'Export history entry deleted.']);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'project_id' => 'nullable|integer|exists:project_tbl,project_id',
            'status' => 'nullable|string|max:50|exists:project_tbl,status',
            'classification' => ['nullable', Rule::in(['direct', 'admin'])],
            'category_id' => 'nullable|integer|exists:inventory_category_tbl,inventory_category_id',
            'supplier_id' => 'nullable|integer|exists:supplier_tbl,supplier_id',
            'stock_status' => ['nullable', Rule::in(['Out of Stock', 'Reorder Needed', 'Sufficient'])],
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        return array_filter($validated, fn ($value) => $value !== null && $value !== '');
    }

    private function datasetRows(string $dataset, array $filters): Collection
    {
        $query = match ($dataset) {
            'project' => $this->projectQuery($filters), 'finance' => $this->financeQuery($filters),
            'budget' => $this->budgetQuery($filters), 'inventory' => $this->inventoryQuery($filters),
            'supplier' => $this->supplierQuery($filters),
        };

        return $query->limit(10000)->get()->map(fn ($row) => collect((array) $row)
            ->map(fn ($value) => is_numeric($value) ? (float) $value : $value)->all());
    }

    private function projectQuery(array $filters): Builder
    {
        $query = DB::table('project_tbl as p')->leftJoin('budgets_tbl as b', 'b.project_id', '=', 'p.project_id')
            ->select(['p.project_id', 'p.project_name', 'p.client_name', 'p.project_manager', 'p.phase', 'p.status',
                'p.start_date', 'p.estimated_end_date', 'p.completion_percentage', 'p.worker_count',
                DB::raw('COALESCE(b.budget_amount, 0) as budget_amount'),
                DB::raw('COALESCE(b.actual_amount, 0) as actual_amount'),
                DB::raw('COALESCE(b.budget_amount, 0) - COALESCE(b.actual_amount, 0) as variance')]);
        $this->applyProjectFilters($query, $filters, 'p');

        return $query->orderByDesc('p.start_date')->orderBy('p.project_name');
    }

    private function financeQuery(array $filters): Builder
    {
        $query = DB::table('fin_expense_tbl as fe')->join('fin_expense_category_tbl as fc', 'fc.fin_category_id', '=', 'fe.fin_category_id')
            ->leftJoin('project_tbl as p', 'p.project_id', '=', 'fe.project_id')
            ->select(['fe.fin_expense_id', 'fe.expense_date', DB::raw("COALESCE(p.project_name, 'OFFICE') as project_name"),
                'fc.category_name', 'fc.classification', 'fe.expense_description', 'fe.amount', 'fe.remarks']);
        if (! empty($filters['project_id'])) {
            $query->where('fe.project_id', $filters['project_id']);
        }
        if (! empty($filters['classification'])) {
            $query->where('fc.classification', $filters['classification']);
        }
        $this->applyDateFilters($query, $filters, 'fe.expense_date');
        if (! empty($filters['search'])) {
            $search = $this->escapeLike($filters['search']);
            $query->where(fn ($inner) => $inner->where('p.project_name', 'like', "%{$search}%")
                ->orWhere('fc.category_name', 'like', "%{$search}%")->orWhere('fe.expense_description', 'like', "%{$search}%")
                ->orWhere('fe.remarks', 'like', "%{$search}%"));
        }

        return $query->orderByDesc('fe.expense_date')->orderByDesc('fe.fin_expense_id');
    }

    private function budgetQuery(array $filters): Builder
    {
        $query = DB::table('budgets_tbl as b')->join('project_tbl as p', 'p.project_id', '=', 'b.project_id')
            ->select(['b.budget_id', 'p.project_name', 'p.client_name', 'p.status', 'b.budget_amount',
                DB::raw('COALESCE(b.actual_amount, 0) as actual_amount'),
                DB::raw('b.budget_amount - COALESCE(b.actual_amount, 0) as remaining_amount'),
                DB::raw('CASE WHEN b.budget_amount > 0 THEN ROUND(COALESCE(b.actual_amount, 0) / b.budget_amount * 100, 2) ELSE 0 END as utilization_percentage')]);
        $this->applyProjectFilters($query, $filters, 'p');

        return $query->orderByDesc('p.start_date')->orderBy('p.project_name');
    }

    private function inventoryQuery(array $filters): Builder
    {
        $lastMovement = DB::table('inventory_transaction_tbl')
            ->select('item_id', DB::raw('MAX(transaction_date) as last_transaction_date'))->groupBy('item_id');
        $statusSql = "CASE WHEN COALESCE(i.current_stock, 0) <= 0 THEN 'Out of Stock' WHEN COALESCE(i.current_stock, 0) <= COALESCE(i.reorder_level, 0) THEN 'Reorder Needed' ELSE 'Sufficient' END";
        $query = DB::table('inventory_item_tbl as i')
            ->leftJoin('inventory_category_tbl as c', 'c.inventory_category_id', '=', 'i.inventory_category_id')
            ->leftJoin('supplier_tbl as s', 's.supplier_id', '=', 'i.supplier_id')
            ->leftJoin('unit_tbl as u', 'u.unit_id', '=', 'i.unit_id')
            ->leftJoinSub($lastMovement, 'movement', 'movement.item_id', '=', 'i.item_id')
            ->select(['i.item_id', 'i.item_name',
                DB::raw("COALESCE(c.inventory_category_name, 'Uncategorized') as category_name"),
                DB::raw("COALESCE(s.supplier_name, 'Unassigned') as supplier_name"),
                DB::raw("COALESCE(u.unit_name, '') as unit_name"),
                DB::raw('COALESCE(i.current_stock, 0) as current_stock'),
                DB::raw('COALESCE(i.reorder_level, 0) as reorder_level'), DB::raw("{$statusSql} as stock_status"),
                'movement.last_transaction_date']);
        if (! empty($filters['category_id'])) {
            $query->where('i.inventory_category_id', $filters['category_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $query->where('i.supplier_id', $filters['supplier_id']);
        }
        if (! empty($filters['stock_status'])) {
            $query->whereRaw("{$statusSql} = ?", [$filters['stock_status']]);
        }
        if (! empty($filters['search'])) {
            $search = $this->escapeLike($filters['search']);
            $query->where(fn ($inner) => $inner->where('i.item_name', 'like', "%{$search}%")
                ->orWhere('c.inventory_category_name', 'like', "%{$search}%")->orWhere('s.supplier_name', 'like', "%{$search}%"));
        }

        return $query->orderBy('stock_status')->orderBy('i.item_name');
    }

    private function supplierQuery(array $filters): Builder
    {
        $lastDelivery = DB::table('inventory_transaction_tbl as it')->join('inventory_item_tbl as ii', 'ii.item_id', '=', 'it.item_id')
            ->where('it.transaction_type', 'IN')->groupBy('ii.supplier_id')
            ->select('ii.supplier_id', DB::raw('MAX(it.transaction_date) as last_delivery_date'));
        $query = DB::table('supplier_tbl as s')->leftJoin('inventory_item_tbl as i', 'i.supplier_id', '=', 's.supplier_id')
            ->leftJoinSub($lastDelivery, 'delivery', 'delivery.supplier_id', '=', 's.supplier_id')
            ->groupBy('s.supplier_id', 's.supplier_name', 's.address', 's.contact_number', 'delivery.last_delivery_date')
            ->select(['s.supplier_id', 's.supplier_name', 's.address', 's.contact_number',
                DB::raw('COUNT(i.item_id) as item_count'), DB::raw('COALESCE(SUM(i.current_stock), 0) as total_stock'),
                DB::raw('SUM(CASE WHEN i.item_id IS NOT NULL AND COALESCE(i.current_stock, 0) <= COALESCE(i.reorder_level, 0) THEN 1 ELSE 0 END) as low_stock_items'),
                'delivery.last_delivery_date']);
        if (! empty($filters['supplier_id'])) {
            $query->where('s.supplier_id', $filters['supplier_id']);
        }
        if (! empty($filters['search'])) {
            $search = $this->escapeLike($filters['search']);
            $query->where(fn ($inner) => $inner->where('s.supplier_name', 'like', "%{$search}%")
                ->orWhere('s.address', 'like', "%{$search}%")->orWhere('s.contact_number', 'like', "%{$search}%"));
        }

        return $query->orderBy('s.supplier_name');
    }

    private function applyProjectFilters(Builder $query, array $filters, string $alias): void
    {
        if (! empty($filters['project_id'])) {
            $query->where("{$alias}.project_id", $filters['project_id']);
        }
        if (! empty($filters['status'])) {
            $query->where("{$alias}.status", $filters['status']);
        }
        $this->applyDateFilters($query, $filters, "{$alias}.start_date");
        if (! empty($filters['search'])) {
            $search = $this->escapeLike($filters['search']);
            $query->where(fn ($inner) => $inner->where("{$alias}.project_name", 'like', "%{$search}%")
                ->orWhere("{$alias}.client_name", 'like', "%{$search}%")
                ->orWhere("{$alias}.project_manager", 'like', "%{$search}%")
                ->orWhere("{$alias}.phase", 'like', "%{$search}%"));
        }
    }

    private function applyDateFilters(Builder $query, array $filters, string $column): void
    {
        if (! empty($filters['start_date'])) {
            $query->whereDate($column, '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate($column, '<=', $filters['end_date']);
        }
    }

    private function datasetKpis(string $dataset, Collection $rows): array
    {
        $money = fn (float $value) => '₱'.number_format($value, 2);

        return match ($dataset) {
            'project' => [
                ['label' => 'Projects', 'value' => (string) $rows->count()],
                ['label' => 'Active', 'value' => (string) $rows->whereNotIn('status', ['Completed', 'Pending'])->count()],
                ['label' => 'Average Completion', 'value' => number_format((float) $rows->avg('completion_percentage'), 1).'%'],
                ['label' => 'Total Budget', 'value' => $money((float) $rows->sum('budget_amount'))],
            ],
            'finance' => [
                ['label' => 'Expense Entries', 'value' => (string) $rows->count()],
                ['label' => 'Total Expenses', 'value' => $money((float) $rows->sum('amount'))],
                ['label' => 'Direct Expenses', 'value' => $money((float) $rows->where('classification', 'direct')->sum('amount'))],
                ['label' => 'Admin Expenses', 'value' => $money((float) $rows->where('classification', 'admin')->sum('amount'))],
            ],
            'budget' => [
                ['label' => 'Projects', 'value' => (string) $rows->count()],
                ['label' => 'Total Budget', 'value' => $money((float) $rows->sum('budget_amount'))],
                ['label' => 'Actual Cost', 'value' => $money((float) $rows->sum('actual_amount'))],
                ['label' => 'Remaining', 'value' => $money((float) $rows->sum('remaining_amount'))],
            ],
            'inventory' => [
                ['label' => 'Items', 'value' => (string) $rows->count()],
                ['label' => 'Total Units in Stock', 'value' => number_format((float) $rows->sum('current_stock'), 2)],
                ['label' => 'Reorder Needed', 'value' => (string) $rows->where('stock_status', 'Reorder Needed')->count()],
                ['label' => 'Out of Stock', 'value' => (string) $rows->where('stock_status', 'Out of Stock')->count()],
            ],
            'supplier' => [
                ['label' => 'Suppliers', 'value' => (string) $rows->count()],
                ['label' => 'Items Covered', 'value' => number_format((float) $rows->sum('item_count'))],
                ['label' => 'Stock Supplied', 'value' => number_format((float) $rows->sum('total_stock'), 2)],
                ['label' => 'Low-stock Items', 'value' => number_format((float) $rows->sum('low_stock_items'))],
            ],
        };
    }

    private function datasetChart(string $dataset, Collection $rows): array
    {
        return match ($dataset) {
            'project' => $this->groupedChart($rows, 'status', null, 'Projects by Status'),
            'finance' => $this->groupedChart($rows, 'classification', 'amount', 'Expenses by Classification'),
            'budget' => ['title' => 'Budget vs Actual by Project', 'labels' => $rows->take(12)->pluck('project_name')->values(),
                'series' => [['label' => 'Budget', 'values' => $rows->take(12)->pluck('budget_amount')->values()],
                    ['label' => 'Actual', 'values' => $rows->take(12)->pluck('actual_amount')->values()]]],
            'inventory' => $this->groupedChart($rows, 'stock_status', null, 'Items by Stock Status'),
            'supplier' => ['title' => 'Items per Supplier', 'labels' => $rows->take(12)->pluck('supplier_name')->values(),
                'series' => [['label' => 'Items', 'values' => $rows->take(12)->pluck('item_count')->values()]]],
        };
    }

    private function groupedChart(Collection $rows, string $group, ?string $sum, string $title): array
    {
        $grouped = $rows->groupBy(fn ($row) => $row[$group] ?: 'Unspecified')
            ->map(fn (Collection $items) => $sum ? (float) $items->sum($sum) : $items->count());

        return ['title' => $title, 'labels' => $grouped->keys()->values(),
            'series' => [['label' => $sum ? 'Amount' : 'Count', 'values' => $grouped->values()]]];
    }

    private function buildCsv(string $title, array $definition, array $columns, array $sections, array $filters, Collection $rows, array $kpis, array $chart): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        if (in_array('summary', $sections, true)) {
            fputcsv($stream, [$title]);
            fputcsv($stream, ['Report Type', $definition['title']]);
            fputcsv($stream, ['Generated At', now()->format('Y-m-d H:i:s')]);
            fputcsv($stream, ['Generated By', Auth::user()->name]);
            foreach ($filters as $key => $value) {
                fputcsv($stream, ['Filter: '.Str::headline($key), $value]);
            }
            fputcsv($stream, []);
        }
        if (in_array('kpis', $sections, true)) {
            fputcsv($stream, ['KPI', 'Value']);
            foreach ($kpis as $kpi) {
                fputcsv($stream, [$kpi['label'], $kpi['value']]);
            }
            fputcsv($stream, []);
        }
        if (in_array('chart', $sections, true)) {
            fputcsv($stream, [$chart['title']]);
            fputcsv($stream, array_merge(['Series'], $chart['labels']->all()));
            foreach ($chart['series'] as $series) {
                fputcsv($stream, array_merge([$series['label']], $series['values']->all()));
            }
            fputcsv($stream, []);
        }
        if (in_array('data', $sections, true)) {
            fputcsv($stream, array_map(fn ($column) => $definition['columns'][$column], $columns));
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($column) => $row[$column] ?? '', $columns));
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function authorizeDataset(string $dataset): array
    {
        abort_unless(isset(self::DATASETS[$dataset]), 404);
        $definition = self::DATASETS[$dataset];
        abort_unless(in_array($this->role(), $definition['roles'], true), 403, 'This report is not available for your role.');

        return $definition;
    }

    private function authorizeReport(Report $report, bool $delete = false): void
    {
        $role = $this->role();
        abort_if($role !== 'admin' && $report->role !== $role, 403);
        abort_if($delete && $role !== 'admin' && $report->user_id !== Auth::id(), 403);
    }

    private function role(): string
    {
        return strtolower((string) Auth::user()->role);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes(trim($value), '%_\\');
    }
}
