<?php
namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    private function amountColumnFor(string $categoryName): string
    {
        return match (strtolower(trim($categoryName))) {
            'labor'     => 'labor_amount',
            'material'  => 'material_amount',
            'equipment' => 'equipment_amount',
            default     => 'other_amount',
        };
    }

    // GET /api/expenses
    public function index()
    {
        $expenses = Expense::with(['project:project_id,project_name', 'category:expense_category_id,category_name'])
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn ($e) => $this->present($e));

        return response()->json($expenses);
    }

    // POST /api/expenses
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_category_id'      => 'required|exists:expense_category_tbl,expense_category_id',
            'expense_description'      => 'required|string|max:255',
            'amount'                   => 'required|numeric|min:0.01',
            'expense_date'             => 'required|date',
            'remarks'                  => 'nullable|string',
            'project_id'               => 'nullable|exists:project_tbl,project_id',
            'inventory_transaction_id' => 'nullable|integer', // adjust exists:table,col to your real inventory_transaction_tbl PK
            'unit_id'                  => 'nullable|integer', // adjust exists:table,col to your real unit table
            'proof_file'               => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = ExpenseCategory::findOrFail($request->expense_category_id);
        $column = $this->amountColumnFor($category->category_name);

        $data = [
            'project_id'               => $request->project_id,
            'expense_category_id'      => $request->expense_category_id,
            'inventory_transaction_id' => $request->inventory_transaction_id,
            'unit_id'                  => $request->unit_id,
            'expense_description'      => $request->expense_description,
            $column                    => $request->amount,
            'expense_date'             => $request->expense_date,
            'remarks'                  => $request->remarks,
        ];

        if ($request->hasFile('proof_file')) {
            $data['proof_file_path'] = $request->file('proof_file')->store('proofs', 'public');
            $data['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
        }

        $expense = Expense::create($data);

        if ($expense->project_id) {
            $this->recalcBudgetActual($expense->project_id);
        }

        $expense->load(['project:project_id,project_name', 'category:expense_category_id,category_name']);
        $this->notifications->notify(
            title: 'New Expense Recorded',
            message: $this->expenseNotificationMessage($expense, (float) $request->amount),
            type: 'new_expense',
            kind: 'info',
            filter: 'alerts',
            referenceType: 'expense',
            referenceId: (int) $expense->expense_id,
        );

        return response()->json($this->present($expense), 201);
    }

    // PUT /api/expenses/{id}
    public function update(Request $request, int $id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'required|exists:expense_category_tbl,expense_category_id',
            'expense_description' => 'required|string|max:255',
            'amount'               => 'required|numeric|min:0.01',
            'expense_date'         => 'required|date',
            'remarks'              => 'nullable|string',
            'project_id'           => 'nullable|exists:project_tbl,project_id',
            'proof_file'           => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldProjectId = $expense->project_id;
        $category = ExpenseCategory::findOrFail($request->expense_category_id);
        $column = $this->amountColumnFor($category->category_name);

        $fillData = [
            'labor_amount' => null, 'material_amount' => null,
            'equipment_amount' => null, 'other_amount' => null,
            $column                => $request->amount,
            'expense_category_id'  => $request->expense_category_id,
            'expense_description'  => $request->expense_description,
            'expense_date'         => $request->expense_date,
            'remarks'              => $request->remarks,
            'project_id'           => $request->project_id ?? $expense->project_id,
        ];

        if ($request->hasFile('proof_file')) {
            if ($expense->proof_file_path) {
                Storage::disk('public')->delete($expense->proof_file_path);
            }
            $fillData['proof_file_path'] = $request->file('proof_file')->store('proofs', 'public');
            $fillData['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
        } elseif ($request->boolean('remove_proof_file')) {
            if ($expense->proof_file_path) {
                Storage::disk('public')->delete($expense->proof_file_path);
            }
            $fillData['proof_file_path'] = null;
            $fillData['proof_file_name'] = null;
        }

        $expense->fill($fillData);
        $expense->save();

        foreach (array_filter(array_unique([$oldProjectId, $expense->project_id])) as $pid) {
            $this->recalcBudgetActual($pid);
        }

        $expense->load(['project:project_id,project_name', 'category:expense_category_id,category_name']);
        return response()->json($this->present($expense));
    }

    // DELETE /api/expenses/{id}
    public function destroy(int $id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        if ($expense->proof_file_path) {
            Storage::disk('public')->delete($expense->proof_file_path);
        }

        $projectId = $expense->project_id;
        $expense->delete();

        if ($projectId) {
            $this->recalcBudgetActual($projectId);
        }

        return response()->json(['message' => 'Expense deleted']);
    }

    // Keeps budgets_tbl.actual_amount in sync with real spend per project.
    private function recalcBudgetActual(int $projectId): void
    {
        $total = Expense::where('project_id', $projectId)->sum(
            DB::raw('COALESCE(labor_amount,0)+COALESCE(material_amount,0)+COALESCE(equipment_amount,0)+COALESCE(other_amount,0)')
        );
        Budget::where('project_id', $projectId)->update(['actual_amount' => $total]);
    }

    // Collapses the 4 amount columns into one 'actual_amount' field for the app.
    private function present(Expense $e): array
    {
        return [
            'expense_id'          => $e->expense_id,
            'project_id'          => $e->project_id,
            'project_name'        => $e->project?->project_name,
            'expense_description' => $e->expense_description,
            'expense_category_id' => $e->expense_category_id,
            'category_name'       => $e->category?->category_name,
            'actual_amount'       => $e->labor_amount ?? $e->material_amount ?? $e->equipment_amount ?? $e->other_amount ?? 0,
            'expense_date'        => $e->expense_date,
            'remarks'             => $e->remarks,
            'proof_file_path'     => $e->proof_file_path,
            'proof_file_name'     => $e->proof_file_name,
        ];
    }

    private function expenseNotificationMessage(Expense $expense, float $amount): string
    {
        $category = $expense->category?->category_name ?? 'Expense';
        $project = $expense->project?->project_name ? " for {$expense->project->project_name}" : '';
        $formatted = 'PHP ' . number_format($amount, 2);

        return "{$category}{$project}: {$expense->expense_description} ({$formatted}).";
    }
}
