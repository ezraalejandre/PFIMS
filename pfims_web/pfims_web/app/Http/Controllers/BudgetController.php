<?php
namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;

class BudgetController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }
    // GET /api/budgets
    public function index()
    {
        $budgets = Budget::with('project:project_id,project_name')->get()->map(fn ($b) => [
            'budget_id'     => $b->budget_id,
            'project_id'    => $b->project_id,
            'project_name'  => $b->project?->project_name,
            'budget_amount' => $b->budget_amount,
            'actual_amount' => $b->actual_amount,
            'proof_file_path' => $b->proof_file_path,
            'proof_file_name' => $b->proof_file_name,
        ]);

        return response()->json($budgets);
    }

    // POST /api/budgets — upsert, one row per project
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id'    => 'required|exists:project_tbl,project_id',
            'budget_amount' => 'required|numeric|min:0',
            'proof_file'    => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = ['budget_amount' => $request->budget_amount];

        $existingBudget = Budget::where('project_id', $request->project_id)->first();

        if ($request->hasFile('proof_file')) {
            if ($existingBudget && $existingBudget->proof_file_path) {
                Storage::disk('public')->delete($existingBudget->proof_file_path);
            }
            $data['proof_file_path'] = $request->file('proof_file')->store('proofs', 'public');
            $data['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
        } elseif ($request->boolean('remove_proof_file')) {
            if ($existingBudget && $existingBudget->proof_file_path) {
                Storage::disk('public')->delete($existingBudget->proof_file_path);
            }
            $data['proof_file_path'] = null;
            $data['proof_file_name'] = null;
        }

        $budget = Budget::updateOrCreate(
            ['project_id' => $request->project_id],
            $data
        );

        $budget->load('project:project_id,project_name');

        $this->notifications->notify(
            title: $existingBudget ? 'Budget Updated' : 'New Budget Allocated',
            message: $existingBudget
                ? "Budget for \"{$budget->project?->project_name}\" updated to PHP " . number_format((float) $budget->budget_amount, 2) . '.'
                : 'Budget of PHP ' . number_format((float) $budget->budget_amount, 2) . " allocated for \"{$budget->project?->project_name}\".",
            type: $existingBudget ? 'budget_updated' : 'new_budget',
            kind: 'info',
            filter: 'alerts',
            referenceType: 'budget',
            referenceId: (int) $budget->budget_id,
        );

        return response()->json([
            'budget_id'     => $budget->budget_id,
            'project_id'    => $budget->project_id,
            'project_name'  => $budget->project?->project_name,
            'budget_amount' => $budget->budget_amount,
            'actual_amount' => $budget->actual_amount,
            'proof_file_path' => $budget->proof_file_path,
            'proof_file_name' => $budget->proof_file_name,
        ], 201);
    }

    // PUT /api/budgets/{id}
public function update(Request $request, int $id)
{
    $budget = Budget::find($id);
    if (!$budget) {
        return response()->json(['message' => 'Budget not found'], 404);
    }

    $validator = Validator::make($request->all(), [
        'budget_amount' => 'required|numeric|min:0',
    ]);
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $budget->update(['budget_amount' => $request->budget_amount]);
    $budget->load('project:project_id,project_name');

    return response()->json([
        'budget_id'     => $budget->budget_id,
        'project_id'    => $budget->project_id,
        'project_name'  => $budget->project?->project_name,
        'budget_amount' => $budget->budget_amount,
        'actual_amount' => $budget->actual_amount,
        'proof_file_path' => $budget->proof_file_path,
        'proof_file_name' => $budget->proof_file_name,
    ]);
}

// DELETE /api/budgets/{id}
public function destroy(int $id)
{
    $budget = Budget::find($id);
    if (!$budget) {
        return response()->json(['message' => 'Budget not found'], 404);
    }

    $budget->delete();

    return response()->json(['message' => 'Budget deleted']);
}
}