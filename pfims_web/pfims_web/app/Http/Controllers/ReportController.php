<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use App\Services\ProjectCostLedger;
use App\Services\ReportFileBuilder;

use App\Models\Report;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
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
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    private const REPORT_TABS = ['expense_summary', 'inventory'];

    private const SUMMARY_COLUMNS = [
        'project_name' => 'Project Name',
        'construction_supply' => 'Construction Supply',
        'salaries_wages' => 'Salaries & Wages',
        'permits_taxes_licenses' => 'Permits, Taxes & Licenses',
        'transportation_expenses' => 'Transportation Expenses',
        'utilities' => 'Utilities (Water & Power)',
        'delivery_expense' => 'Delivery Expense',
        'others' => 'Others (SOS, Construction Bond, etc.)',
        'administrative_expenses' => 'Administrative Expenses',
        'total' => 'Total',
    ];

    private const DATASETS = [
        'expense_summary' => [
            'title' => 'Expenses Summary', 'type' => 'finance', 'roles' => ['admin', 'accounting'],
            'columns' => self::SUMMARY_COLUMNS,
            'filters' => ['search', 'report_month', 'report_day', 'report_year', 'project_status', 'expense_type'],
        ],
        'contracts' => [
            'title' => 'Contracts', 'type' => 'finance', 'roles' => ['admin', 'accounting'],
            'columns' => [
                'project_name' => 'Project', 'start_date' => 'Start Date', 'actual_end_date' => 'End Date',
                'original_contract_price' => 'Contract Price', 'additional_works_contract' => 'Addl. Works',
                'total_contract_price' => 'Total Contract', 'original_payment_received' => 'Original Payment',
                'additional_works_payment' => 'Addl. Payment', 'total_payment' => 'Total Payment',
                'project_expense' => 'Project Expense', 'accounts_receivable' => 'Accounts Receivable',
                'profit_loss_payment_basis' => 'Profit/Loss (Payment)',
                'profit_loss_contract_basis' => 'Profit/Loss (Contract)',
            ],
            'filters' => ['search', 'project_id', 'status', 'start_date', 'end_date'],
        ],
        'project' => [
            'title' => 'Project', 'type' => 'project', 'roles' => ['admin', 'operations'],
            'columns' => [
                'project_id' => 'Project ID', 'project_name' => 'Project', 'client_name' => 'Client',
                'project_manager' => 'Project Manager', 'phase' => 'Phase', 'status' => 'Status',
                'start_date' => 'Start Date', 'estimated_end_date' => 'Estimated End',
                'completion_percentage' => 'Completion (%)', 'worker_count' => 'Workers',
                'budget_amount' => 'Budget', 'direct_cost' => 'Direct Project Expenses',
                'inventory_usage_cost' => 'Inventory Usage Cost', 'unvalued_withdrawals' => 'Unvalued Withdrawals',
                'actual_amount' => 'Actual Cost', 'variance' => 'Budget Difference',
            ],
            'filters' => ['search', 'project_id', 'status', 'start_date', 'end_date'],
        ],
        'finance' => [
            'title' => 'Expenses', 'type' => 'finance', 'roles' => ['admin', 'accounting'],
            'columns' => [
                'fin_expense_id' => 'Expense ID', 'expense_date' => 'Expense Date',
                'project_name' => 'Project / Cost Center', 'category_name' => 'Category',
                'classification' => 'Classification', 'cost_role' => 'Cost Role', 'expense_description' => 'Description',
                'amount' => 'Amount', 'remarks' => 'Remarks',
            ],
            'filters' => ['search', 'project_id', 'classification', 'start_date', 'end_date'],
        ],
        'budget' => [
            'title' => 'Budget', 'type' => 'budget', 'roles' => ['admin', 'accounting'],
            'columns' => [
                'budget_id' => 'Budget ID', 'project_name' => 'Project', 'client_name' => 'Client',
                'status' => 'Project Status', 'budget_amount' => 'Budget',
                'direct_cost' => 'Direct Project Expenses', 'inventory_usage_cost' => 'Inventory Usage Cost',
                'unvalued_withdrawals' => 'Unvalued Withdrawals', 'actual_amount' => 'Actual Cost',
                'remaining_amount' => 'Remaining', 'utilization_percentage' => 'Utilization (%)',
            ],
            'filters' => ['search', 'project_id', 'status', 'start_date', 'end_date'],
        ],
        'inventory' => [
            'title' => 'Inventory', 'type' => 'inventory', 'roles' => ['admin', 'operations'],
            'columns' => [
                'item_id' => 'Item ID', 'item_name' => 'Item', 'category_name' => 'Category',
                'supplier_name' => 'Supplier', 'unit_name' => 'Unit', 'unit_price' => 'Unit Price', 'current_stock' => 'Current Stock',
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

    public function page(): View
    {
        $role = $this->role();

        return view('reports', [
            'portal' => in_array($role, ['admin', 'accounting', 'operations'], true) ? $role : 'admin',
            'reportCatalog' => $this->catalogPayload($role),
        ]);
    }

    public function catalog(): JsonResponse
    {
        return response()->json($this->catalogPayload($this->role()));
    }

    private function catalogPayload(string $role): array
    {
        $datasets = collect(self::DATASETS)
            ->only(self::REPORT_TABS)
            ->filter(fn (array $definition) => in_array($role, $definition['roles'], true))
            ->map(fn (array $definition, string $key) => [
                'key' => $key, 'title' => $definition['title'], 'type' => $definition['type'],
                'columns' => $definition['columns'], 'filters' => $definition['filters'],
            ])->values();

        return [
            'datasets' => $datasets,
            'options' => [
                'projects' => DB::table('project_tbl')->orderBy('project_name')->get(['project_id as value', 'project_name as label']),
                'statuses' => DB::table('project_tbl')->whereNotNull('status')->distinct()->pluck('status')
                    ->sortBy(fn (string $status) => $status === 'Ongoing' ? '0' : '1'.$status)->values(),
                'categories' => DB::table('inventory_category_tbl')->orderBy('inventory_category_name')->get(['inventory_category_id as value', 'inventory_category_name as label']),
                'suppliers' => DB::table('supplier_tbl')->orderBy('supplier_name')->get(['supplier_id as value', 'supplier_name as label']),
                'classifications' => ['direct', 'admin', 'office'],
                'stock_statuses' => ['Out of Stock', 'Reorder Needed', 'Sufficient'],
                'formats' => [
                    ['value' => 'xlsx', 'label' => 'Excel (.xlsx)'],
                    ['value' => 'csv', 'label' => 'CSV (.csv)'],
                    ['value' => 'pdf', 'label' => 'PDF (.pdf)'],
                ],
                'sections' => [
                    ['value' => 'summary', 'label' => 'Report and filter summary'],
                    ['value' => 'kpis', 'label' => 'KPI summary'],
                    ['value' => 'chart', 'label' => 'Chart data'],
                    ['value' => 'data', 'label' => 'Detailed rows'],
                ],
            ],
        ];
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
        $balances = $dataset === 'expense_summary' ? $this->summaryBalances($rows, $filters) : [];
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
            'totals' => $balances['totals'] ?? null,
            'previous_totals' => $balances['previous_totals'] ?? null,
            'month_totals' => $balances['month_totals'] ?? null,
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
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'columns' => 'required|array|min:1', 'columns.*' => 'required|string|distinct|max:60',
            'design' => 'nullable|array',
            'design.header_color' => ['nullable', Rule::in(['navy', 'orange', 'green'])],
            'design.table_spacing' => ['nullable', Rule::in(['standard', 'compact'])],
            'sections' => 'nullable|array|min:1',
            'sections.*' => ['required', 'string', 'distinct', Rule::in(['summary', 'kpis', 'chart', 'data'])],
            'filters' => 'nullable|array',
            'row_limit' => 'nullable|integer|min:1|max:10000',
        ]);

        $definition = $this->authorizeDataset($validated['dataset']);
        if (array_diff($validated['columns'], array_keys($definition['columns'])) !== []) {
            throw ValidationException::withMessages(['columns' => 'One or more selected columns are unavailable.']);
        }
        if (! in_array($validated['dataset'], self::REPORT_TABS, true) && $validated['format'] !== 'csv') {
            throw ValidationException::withMessages(['format' => 'This legacy report is available only as CSV.']);
        }

        if (in_array($validated['dataset'], self::REPORT_TABS, true)) {
            return $this->exportCurrentReport($validated, $definition);
        }

        $filterRequest = Request::create('/', 'GET', $validated['filters'] ?? []);
        $filters = $this->validatedFilters($filterRequest);
        $rows = $this->datasetRows($validated['dataset'], $filters);
        $rows = $this->limitExportRows($rows, $validated['row_limit'] ?? null);
        $kpis = $this->datasetKpis($validated['dataset'], $rows);
        $chart = $this->datasetChart($validated['dataset'], $rows);
        $role = $this->role();
        $fileName = (Str::slug($validated['title']) ?: $validated['dataset']).'-'.now()->format('Ymd-His').'-'.strtolower(Str::random(4)).'.csv';
        $filePath = "reports/exports/{$role}/{$fileName}";
        $csv = $this->buildCsv($validated['title'], $definition, $validated['columns'], $validated['sections'] ?? ['summary', 'data'], $filters, $rows, $kpis, $chart);
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
                'filters_applied' => $filters, 'export_options' => ['sections' => $validated['sections'] ?? ['summary', 'data'], 'row_limit' => $validated['row_limit'] ?? null],
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

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset' => ['required', Rule::in(self::REPORT_TABS)],
            'title' => 'required|string|min:3|max:120',
            'columns' => 'required|array|min:1', 'columns.*' => 'required|string|distinct|max:60',
            'design' => 'nullable|array',
            'design.header_color' => ['nullable', Rule::in(['navy', 'orange', 'green'])],
            'design.table_spacing' => ['nullable', Rule::in(['standard', 'compact'])],
            'filters' => 'nullable|array',
            'row_limit' => 'nullable|integer|min:1|max:10000',
        ]);
        $definition = $this->authorizeDataset($validated['dataset']);
        if (array_diff($validated['columns'], array_keys($definition['columns'])) !== []) {
            throw ValidationException::withMessages(['columns' => 'One or more selected columns are unavailable.']);
        }
        $filters = $this->validatedFilters(Request::create('/', 'GET', $validated['filters'] ?? []));
        $rows = $this->datasetRows($validated['dataset'], $filters);
        if ($validated['dataset'] === 'expense_summary') {
            $rows = $this->summaryRowsForColumns($rows, $validated['columns']);
        }
        $rows = $this->limitExportRows($rows, $validated['row_limit'] ?? null);
        $balances = $validated['dataset'] === 'expense_summary'
            ? $this->summaryBalances($rows, $filters, $validated['columns']) : [];
        return response()->json([
            'title' => $validated['title'],
            'scope' => $this->reportScope($validated['dataset'], $filters),
            'as_of' => now()->format('F j, Y'),
            'columns' => array_intersect_key($definition['columns'], array_flip($validated['columns'])),
            'rows' => $rows->take(8)->values(),
            'row_count' => $rows->count(),
            'totals' => $balances['totals'] ?? null,
            'previous_totals' => $balances['previous_totals'] ?? null,
            'month_totals' => $balances['month_totals'] ?? null,
        ]);
    }

    private function exportCurrentReport(array $validated, array $definition): StreamedResponse
    {
        $filters = $this->validatedFilters(Request::create('/', 'GET', $validated['filters'] ?? []));
        $rows = $this->datasetRows($validated['dataset'], $filters);
        $columns = array_intersect_key($definition['columns'], array_flip($validated['columns']));
        if ($validated['dataset'] === 'expense_summary') {
            $rows = $this->summaryRowsForColumns($rows, array_keys($columns));
        }
        $rows = $this->limitExportRows($rows, $validated['row_limit'] ?? null);
        $balances = $validated['dataset'] === 'expense_summary'
            ? $this->summaryBalances($rows, $filters, array_keys($columns)) : [];
        $totals = $balances ? array_values($balances) : null;
        $format = $validated['format'];
        $file = app(ReportFileBuilder::class)->build($format, $validated['title'],
            $this->reportScope($validated['dataset'], $filters), $columns, $rows->all(), $totals, $validated['design'] ?? []);
        $role = $this->role();
        $fileName = (Str::slug($validated['title']) ?: $validated['dataset']).'-'.now()->format('Ymd-His')
            .'-'.strtolower(Str::random(4)).'.'.$format;
        $filePath = "reports/exports/{$role}/{$fileName}";
        Storage::disk('public')->put($filePath, $file);
        try {
            $report = Report::create([
                'report_id' => Report::generateReportId(), 'title' => $validated['title'],
                'type' => $definition['type'], 'role' => $role,
                'description' => 'System-generated '.$definition['title'].' report',
                'file_name' => $fileName, 'file_path' => $filePath,
                'file_size' => Storage::disk('public')->size($filePath),
                'date_uploaded' => today()->toDateString(), 'uploaded_by' => Auth::user()->name,
                'status' => 'Completed', 'generation_method' => 'system_export',
                'dataset_key' => $validated['dataset'], 'export_format' => $format,
                'row_count' => $rows->count(), 'selected_columns' => array_keys($columns),
                'filters_applied' => $filters, 'export_options' => ['sections' => ['summary', 'data'], 'design' => $validated['design'] ?? [], 'row_limit' => $validated['row_limit'] ?? null],
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

    private function reportScope(string $dataset, array $filters = []): string
    {
        return match ($dataset) {
            'expense_summary' => ($filters['project_status'] ?? 'Ongoing') === 'Completed'
                ? 'COMPLETED PROJECTS' : 'ONGOING PROJECTS',
            'contracts' => 'PROJECT CONTRACTS',
            'inventory' => 'INVENTORY ITEMS',
        };
    }

    public function download(string $id): StreamedResponse|JsonResponse
    {
        $report = Report::query()->generated()->where('report_id', $id)->firstOrFail();
        $this->authorizeReport($report);
        if (! Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'The exported file is no longer available.'], 404);
        }

        $this->audit->recordOperation('EXPORT', 'Reports', 'reports', 'Downloaded exported report: '.$report->title.' ('.$report->report_id.')');

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
        foreach (['report_month', 'report_day', 'report_year'] as $datePart) {
            $value = $request->input($datePart);
            if (is_string($value) && ctype_digit($value)) {
                $request->merge([$datePart => (int) $value]);
            }
        }
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'report_month' => 'nullable|integer|between:1,12',
            'report_day' => 'nullable|integer|between:1,31',
            'report_year' => 'nullable|integer|between:1900,2100',
            'project_status' => ['nullable', Rule::in(['Ongoing', 'Completed'])],
            'expense_type' => ['nullable', Rule::in(['Overall', 'Direct', 'Admin'])],
            'project_id' => 'nullable|integer|exists:project_tbl,project_id',
            'status' => 'nullable|string|max:50|exists:project_tbl,status',
            'classification' => ['nullable', Rule::in(['direct', 'admin', 'office'])],
            'category_id' => 'nullable|integer|exists:inventory_category_tbl,inventory_category_id',
            'supplier_id' => 'nullable|integer|exists:supplier_tbl,supplier_id',
            'stock_status' => ['nullable', Rule::in(['Out of Stock', 'Reorder Needed', 'Sufficient'])],
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        return array_filter($validated, fn ($value) => $value !== null && $value !== '');
    }

    private function datasetRows(string $dataset, array $filters): Collection
    {
        if ($dataset === 'expense_summary') {
            return $this->expenseSummaryRows($filters);
        }
        if ($dataset === 'contracts') {
            return $this->contractRows($filters);
        }
        $query = match ($dataset) {
            'project' => $this->projectQuery($filters), 'finance' => $this->financeQuery($filters),
            'budget' => $this->budgetQuery($filters), 'inventory' => $this->inventoryQuery($filters),
            'supplier' => $this->supplierQuery($filters),
        };

        return $query->limit(10000)->get()->map(fn ($row) => collect((array) $row)
            ->map(fn ($value) => is_numeric($value) ? (float) $value : $value)->all());
    }

    private function expenseSummaryRows(array $filters, ?string $cutoffDate = null): Collection
    {
        $cutoffDate ??= $this->summaryCutoffDate($filters)->toDateString();
        $projects = DB::table('project_tbl as p')
            ->select('p.project_id', 'p.project_name', 'p.status');
        if (($filters['project_status'] ?? 'Ongoing') === 'Completed') {
            $projects->where('p.status', 'Completed');
        } else {
            $projects->whereNotIn('p.status', ['Completed', 'Pending']);
        }
        if (! empty($filters['project_id'])) {
            $projects->where('p.project_id', $filters['project_id']);
        }
        if (! empty($filters['status'])) {
            $projects->where('p.status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $projects->where('p.project_name', 'like', '%'.$this->escapeLike($filters['search']).'%');
        }
        $projects = $projects->orderBy('p.project_name')->get();
        $ids = $projects->pluck('project_id')->all();
        $blank = array_fill_keys(array_keys(self::SUMMARY_COLUMNS), 0.0);
        $rows = $projects->map(function ($project) use ($blank) {
            return array_merge($blank, ['project_id' => (int) $project->project_id,
                'project_name' => $project->project_name]);
        })->keyBy('project_id');

        if ($ids !== []) {
            $expenses = DB::table('fin_expense_tbl as e')
                ->join('fin_expense_category_tbl as c', 'c.fin_category_id', '=', 'e.fin_category_id')
                ->whereIn('e.project_id', $ids)
                ->select('e.project_id', 'e.amount', 'c.category_code', 'c.classification');
            if (\Illuminate\Support\Facades\Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id')) {
                $expenses->whereNull('e.inventory_transaction_id');
            }
            $this->applyDateFilters($expenses, $filters, 'e.expense_date');
            $expenses->whereDate('e.expense_date', '<=', $cutoffDate);
            if (($filters['expense_type'] ?? 'Overall') !== 'Overall') {
                $expenses->where('c.classification', strtolower($filters['expense_type']));
            } else {
                $expenses->whereIn('c.classification', ['direct', 'admin']);
            }
            $categoryColumns = [
                'CONSTRUCTION_SUPPLY' => 'construction_supply',
                'SALARIES_WAGES' => 'salaries_wages',
                'PERMITS_TAXES' => 'permits_taxes_licenses',
                'PERMIT_TAXES_LICENSES' => 'permits_taxes_licenses',
                'EQUIPMENT_RENTAL' => 'transportation_expenses',
                'TRANSPORTATION_EXPENSES' => 'transportation_expenses',
                'UTILITIES' => 'utilities',
                'UTILITIES_WATER_POWER' => 'utilities',
                'DELIVERY' => 'delivery_expense',
                'DELIVERY_EXPENSE' => 'delivery_expense',
            ];
            foreach ($expenses->get() as $expense) {
                $row = $rows->get((int) $expense->project_id);
                $column = $expense->classification === 'admin' ? 'administrative_expenses'
                    : ($categoryColumns[$expense->category_code] ?? 'others');
                $row[$column] += (float) $expense->amount;
                $row['total'] += (float) $expense->amount;
                $rows->put((int) $expense->project_id, $row);
            }

            if (($filters['expense_type'] ?? 'Overall') !== 'Admin'
                && \Illuminate\Support\Facades\Schema::hasTable('inventory_cost_allocation_tbl')) {
                $allocations = DB::table('inventory_cost_allocation_tbl as a')
                    ->join('inventory_transaction_tbl as movement', 'movement.inventory_transaction_id', '=', 'a.out_transaction_id')
                    ->whereIn('a.project_id', $ids)->where('a.valuation_status', 'valued')
                    ->select('a.project_id', 'a.allocated_amount');
                $this->applyDateFilters($allocations, $filters, 'movement.transaction_date');
                $allocations->whereDate('movement.transaction_date', '<=', $cutoffDate);
                foreach ($allocations->get() as $allocation) {
                    $row = $rows->get((int) $allocation->project_id);
                    $row['construction_supply'] += (float) $allocation->allocated_amount;
                    $row['total'] += (float) $allocation->allocated_amount;
                    $rows->put((int) $allocation->project_id, $row);
                }
            }
        }

        return $rows->values()->map(function (array $row) {
            foreach (self::SUMMARY_COLUMNS as $key => $label) {
                if ($key !== 'project_name') $row[$key] = round((float) $row[$key], 2);
            }
            return $row;
        });
    }

    private function contractRows(array $filters): Collection
    {
        $query = DB::table('fin_project_contract_tbl as c')
            ->join('project_tbl as p', 'p.project_id', '=', 'c.project_id')
            ->leftJoin('budgets_tbl as b', 'b.project_id', '=', 'p.project_id')
            ->select('c.*', 'p.project_name', 'p.start_date', 'p.actual_end_date', 'p.status', 'b.budget_amount');
        $this->applyProjectFilters($query, $filters, 'p');
        $ledger = app(ProjectCostLedger::class);

        return $query->orderBy('p.project_name')->limit(10000)->get()->map(function ($contract) use ($ledger) {
            $price = (float) ($contract->budget_amount ?? $contract->original_contract_price ?? 0);
            $additional = (float) $contract->additional_works_contract;
            $payment = (float) $contract->original_payment_received;
            $additionalPayment = (float) $contract->additional_works_payment;
            $cost = $ledger->forProject((int) $contract->project_id)['total'];
            $totalContract = $price + $additional;
            $totalPayment = $payment + $additionalPayment;
            return [
                'contract_id' => (int) $contract->contract_id,
                'project_id' => (int) $contract->project_id,
                'project_name' => $contract->project_name,
                'start_date' => $contract->start_date,
                'actual_end_date' => $contract->actual_end_date,
                'original_contract_price' => $price,
                'additional_works_contract' => $additional,
                'total_contract_price' => $totalContract,
                'original_payment_received' => $payment,
                'additional_works_payment' => $additionalPayment,
                'total_payment' => $totalPayment,
                'project_expense' => $cost,
                'accounts_receivable' => $totalContract - $totalPayment,
                'profit_loss_payment_basis' => $totalPayment - $cost,
                'profit_loss_contract_basis' => $totalContract - $cost,
                'remarks' => $contract->remarks,
            ];
        });
    }

    private function projectQuery(array $filters): Builder
    {
        $query = DB::table('project_tbl as p')->leftJoin('budgets_tbl as b', 'b.project_id', '=', 'p.project_id');
        $actual = $this->joinProjectCosts($query, 'p.project_id');
        $query
            ->select(['p.project_id', 'p.project_name', 'p.client_name', 'p.project_manager', 'p.phase', 'p.status',
                'p.start_date', 'p.estimated_end_date', 'p.completion_percentage', 'p.worker_count',
                DB::raw('COALESCE(b.budget_amount, 0) as budget_amount'),
                DB::raw('COALESCE(project_direct_cost.direct_cost, 0) as direct_cost'),
                DB::raw($this->allocationColumn('allocated_cost').' as inventory_usage_cost'),
                DB::raw($this->allocationColumn('unvalued_count').' as unvalued_withdrawals'),
                DB::raw("{$actual} as actual_amount"),
                DB::raw("COALESCE(b.budget_amount, 0) - {$actual} as variance")]);
        $this->applyProjectFilters($query, $filters, 'p');

        return $query->orderByDesc('p.start_date')->orderBy('p.project_name');
    }

    private function financeQuery(array $filters): Builder
    {
        $query = DB::table('fin_expense_tbl as fe')->join('fin_expense_category_tbl as fc', 'fc.fin_category_id', '=', 'fe.fin_category_id')
            ->leftJoin('project_tbl as p', 'p.project_id', '=', 'fe.project_id')
            ->select(['fe.fin_expense_id', 'fe.expense_date', DB::raw("COALESCE(p.project_name, 'OFFICE') as project_name"),
                'fc.category_name', 'fc.classification', 'fe.expense_description', 'fe.amount', 'fe.remarks']);
        if (\Illuminate\Support\Facades\Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id')) {
            $query->addSelect(DB::raw("CASE WHEN fe.inventory_transaction_id IS NOT NULL THEN 'Storage Purchase' WHEN fe.project_id IS NOT NULL THEN 'Direct Project Expense' ELSE 'Other Expense' END as cost_role"));
        } else {
            $query->addSelect(DB::raw("CASE WHEN fe.project_id IS NOT NULL THEN 'Direct Project Expense' ELSE 'Other Expense' END as cost_role"));
        }
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
        $query = DB::table('budgets_tbl as b')->join('project_tbl as p', 'p.project_id', '=', 'b.project_id');
        $actual = $this->joinProjectCosts($query, 'p.project_id');
        $query
            ->select(['b.budget_id', 'p.project_name', 'p.client_name', 'p.status', 'b.budget_amount',
                DB::raw('COALESCE(project_direct_cost.direct_cost, 0) as direct_cost'),
                DB::raw($this->allocationColumn('allocated_cost').' as inventory_usage_cost'),
                DB::raw($this->allocationColumn('unvalued_count').' as unvalued_withdrawals'),
                DB::raw("{$actual} as actual_amount"),
                DB::raw("b.budget_amount - {$actual} as remaining_amount"),
                DB::raw("CASE WHEN b.budget_amount > 0 THEN ROUND({$actual} / b.budget_amount * 100, 2) ELSE 0 END as utilization_percentage")]);
        $this->applyProjectFilters($query, $filters, 'p');

        return $query->orderByDesc('p.start_date')->orderBy('p.project_name');
    }

    private function joinProjectCosts(Builder $query, string $projectColumn): string
    {
        $ledger = app(ProjectCostLedger::class);
        $query->leftJoinSub($ledger->directTotals(), 'project_direct_cost',
            fn ($join) => $join->on('project_direct_cost.project_id', '=', $projectColumn));
        if ($allocated = $ledger->allocatedTotals()) {
            $query->leftJoinSub($allocated, 'project_allocated_cost',
                fn ($join) => $join->on('project_allocated_cost.project_id', '=', $projectColumn));

            return 'COALESCE(project_direct_cost.direct_cost, 0) + COALESCE(project_allocated_cost.allocated_cost, 0)';
        }

        return 'COALESCE(project_direct_cost.direct_cost, 0)';
    }

    private function allocationColumn(string $column): string
    {
        return \Illuminate\Support\Facades\Schema::hasTable('inventory_cost_allocation_tbl')
            ? "COALESCE(project_allocated_cost.{$column}, 0)" : '0';
    }

    private function inventoryQuery(array $filters): Builder
    {
        $lastMovement = DB::table('inventory_transaction_tbl')
            ->select('item_id', DB::raw('MAX(transaction_date) as last_transaction_date'))->groupBy('item_id');
        $defaultThreshold = (float) SystemSetting::value('inventory_reorder_threshold', 5);
        $effectiveThresholdSql = "CASE WHEN i.reorder_level IS NULL OR i.reorder_level < {$defaultThreshold} THEN {$defaultThreshold} ELSE i.reorder_level END";
        $statusSql = "CASE WHEN COALESCE(i.current_stock, 0) <= 0 THEN 'Out of Stock' WHEN COALESCE(i.current_stock, 0) <= {$effectiveThresholdSql} THEN 'Reorder Needed' ELSE 'Sufficient' END";
        $query = DB::table('inventory_item_tbl as i')
            ->leftJoin('inventory_category_tbl as c', 'c.inventory_category_id', '=', 'i.inventory_category_id')
            ->leftJoin('supplier_tbl as s', 's.supplier_id', '=', 'i.supplier_id')
            ->leftJoin('unit_tbl as u', 'u.unit_id', '=', 'i.unit_id')
            ->leftJoinSub($lastMovement, 'movement', 'movement.item_id', '=', 'i.item_id')
            ->select(['i.item_id', 'i.item_name', 'i.unit_price',
                DB::raw("COALESCE(c.inventory_category_name, 'Uncategorized') as category_name"),
                DB::raw("COALESCE(s.supplier_name, 'Unassigned') as supplier_name"),
                DB::raw("COALESCE(u.unit_name, '') as unit_name"),
                DB::raw('COALESCE(i.current_stock, 0) as current_stock'),
                DB::raw("{$effectiveThresholdSql} as reorder_level"), DB::raw("{$statusSql} as stock_status"),
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

    private function summaryCutoffDate(array $filters): CarbonImmutable
    {
        $today = CarbonImmutable::now('Asia/Manila');
        try {
            return CarbonImmutable::createSafe(
                (int) ($filters['report_year'] ?? $today->year),
                (int) ($filters['report_month'] ?? $today->month),
                (int) ($filters['report_day'] ?? $today->day),
                0, 0, 0, 'Asia/Manila'
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages(['report_day' => 'Choose a valid day for the selected month and year.']);
        }
    }

    private function datasetKpis(string $dataset, Collection $rows): array
    {
        $money = fn (float $value) => '₱'.number_format($value, 2);

        return match ($dataset) {
            'expense_summary' => [
                ['label' => 'Ongoing Projects', 'value' => (string) $rows->count()],
                ['label' => 'Project Site Expenses', 'value' => $money((float) $rows->sum('total'))],
                ['label' => 'Construction Supply', 'value' => $money((float) $rows->sum('construction_supply'))],
            ],
            'contracts' => [
                ['label' => 'Contracts', 'value' => (string) $rows->count()],
                ['label' => 'Total Contract', 'value' => $money((float) $rows->sum('total_contract_price'))],
                ['label' => 'Total Payment', 'value' => $money((float) $rows->sum('total_payment'))],
                ['label' => 'Accounts Receivable', 'value' => $money((float) $rows->sum('accounts_receivable'))],
            ],
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
                ['label' => 'Storage Purchases', 'value' => $money((float) $rows->where('cost_role', 'Storage Purchase')->sum('amount'))],
            ],
            'budget' => [
                ['label' => 'Projects', 'value' => (string) $rows->count()],
                ['label' => 'Total Budget', 'value' => $money((float) $rows->sum('budget_amount'))],
                ['label' => 'Actual Cost', 'value' => $money((float) $rows->sum('actual_amount'))],
                ['label' => 'Remaining', 'value' => $money((float) $rows->sum('remaining_amount'))],
                ['label' => 'Inventory Usage', 'value' => $money((float) $rows->sum('inventory_usage_cost'))],
            ],
            'inventory' => [
                ['label' => 'Total Items', 'value' => (string) $rows->count()],
                ['label' => 'Low Stock', 'value' => (string) $rows->where('stock_status', 'Reorder Needed')->count()],
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
            'expense_summary', 'contracts' => ['title' => '', 'type' => 'bar', 'labels' => collect(), 'series' => []],
            'project' => $this->groupedChart($rows->where('status', '!=', 'Completed'), 'status', null, 'Project Status Across Active Projects', 'pie'),
            'finance' => $this->groupedChart($rows, 'category_name', 'amount', 'Expenses by Category', 'pie'),
            'budget' => $this->budgetStatusChart($rows),
            'inventory' => $this->inventoryMovementChart($rows),
            'supplier' => ['title' => 'Items per Supplier', 'type' => 'pie', 'labels' => $rows->take(12)->pluck('supplier_name')->values(),
                'series' => [['label' => 'Items', 'values' => $rows->take(12)->pluck('item_count')->values()]]],
        };
    }

    private function summaryTotals(Collection $rows, string $label = 'TOTAL'): array
    {
        $totals = ['project_name' => $label];
        foreach (self::SUMMARY_COLUMNS as $key => $label) {
            if ($key !== 'project_name') $totals[$key] = round((float) $rows->sum($key), 2);
        }
        return $totals;
    }

    private function summaryBalances(Collection $rows, array $filters, ?array $columns = null): array
    {
        $previousCutoff = $this->summaryCutoffDate($filters)->startOfMonth()->subDay()->toDateString();
        $previousRows = $this->expenseSummaryRows($filters, $previousCutoff);
        $previousRows = $previousRows->whereIn('project_id', $rows->pluck('project_id')->all());
        if ($columns !== null) $previousRows = $this->summaryRowsForColumns($previousRows, $columns);
        $current = $this->summaryTotals($rows, 'TOTAL(As of Current Month)');
        $previous = $this->summaryTotals($previousRows, 'TOTAL BALANCE(As of Previous Month)');
        $month = ['project_name' => 'TOTAL(This Month)'];
        foreach (self::SUMMARY_COLUMNS as $key => $label) {
            if ($key !== 'project_name') $month[$key] = round($current[$key] - $previous[$key], 2);
        }
        return ['totals' => $current, 'previous_totals' => $previous, 'month_totals' => $month];
    }

    private function limitExportRows(Collection $rows, ?int $limit): Collection
    {
        return $limit === null ? $rows : $rows->take($limit)->values();
    }

    private function summaryRowsForColumns(Collection $rows, array $columns): Collection
    {
        $categories = array_diff(array_keys(self::SUMMARY_COLUMNS), ['project_name', 'total']);
        $selectedCategories = array_intersect($categories, $columns);

        return $rows->map(function (array $row) use ($selectedCategories): array {
            $row['total'] = round(array_sum(array_map(
                fn (string $key): float => (float) ($row[$key] ?? 0),
                $selectedCategories
            )), 2);

            return $row;
        });
    }

    private function groupedChart(Collection $rows, string $group, ?string $sum, string $title, string $type = 'bar'): array
    {
        $grouped = $rows->groupBy(fn ($row) => $row[$group] ?: 'Unspecified')
            ->map(fn (Collection $items) => $sum ? (float) $items->sum($sum) : $items->count());

        return ['title' => $title, 'type' => $type, 'labels' => $grouped->keys()->values(),
            'series' => [['label' => $sum ? 'Amount' : 'Count', 'values' => $grouped->values()]]];
    }

    private function budgetStatusChart(Collection $rows): array
    {
        $statuses = $rows->map(function (array $row): string {
            $budget = (float) ($row['budget_amount'] ?? 0);
            $actual = (float) ($row['actual_amount'] ?? 0);
            if ($budget <= 0) return 'No Budget';
            if ($actual > $budget) return 'Over Budget';
            return ($actual / $budget) >= 0.8 ? 'Near Limit' : 'On Track';
        })->countBy();
        $labels = collect(['On Track', 'Near Limit', 'Over Budget', 'No Budget'])
            ->filter(fn (string $status) => ($statuses[$status] ?? 0) > 0)->values();

        return ['title' => 'Projects by Budget Status', 'type' => 'pie', 'labels' => $labels,
            'series' => [['label' => 'Projects', 'values' => $labels->map(fn (string $status) => $statuses[$status])]]];
    }

    private function inventoryMovementChart(Collection $rows): array
    {
        $movements = DB::table('inventory_transaction_tbl')
            ->whereIn('item_id', $rows->pluck('item_id')->filter()->all())
            ->select('transaction_date', 'transaction_type', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('transaction_date', 'transaction_type')
            ->orderByDesc('transaction_date')->get()->groupBy('transaction_date')->take(14)->reverse();
        $labels = $movements->keys()->values();

        return ['title' => 'Stock Movement by Date', 'type' => 'horizontalBar', 'labels' => $labels,
            'series' => [
                ['label' => 'IN', 'values' => $labels->map(fn ($date) => (float) optional($movements[$date]->firstWhere('transaction_type', 'IN'))->quantity)],
                ['label' => 'OUT', 'values' => $labels->map(fn ($date) => (float) optional($movements[$date]->firstWhere('transaction_type', 'OUT'))->quantity)],
            ]];
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
