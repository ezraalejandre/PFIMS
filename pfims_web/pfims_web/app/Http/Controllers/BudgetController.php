<?php
namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    // GET /api/budgets
    public function index()
    {
        $budgets = Budget::with('project:project_id,project_name')->get()->map(fn ($b) => [
            'budget_id'     => $b->budget_id,
            'project_id'    => $b->project_id,
            'project_name'  => $b->project?->project_name,
            'budget_amount' => $b->budget_amount,
            'actual_amount' => $b->actual_amount,
        ]);

        return response()->json($budgets);
    }

    // POST /api/budgets — upsert, one row per project
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id'    => 'required|exists:project_tbl,project_id',
            'budget_amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $budget = Budget::updateOrCreate(
            ['project_id' => $request->project_id],
            ['budget_amount' => $request->budget_amount]
        );
        $budget->load('project:project_id,project_name');

        return response()->json([
            'budget_id'     => $budget->budget_id,
            'project_id'    => $budget->project_id,
            'project_name'  => $budget->project?->project_name,
            'budget_amount' => $budget->budget_amount,
            'actual_amount' => $budget->actual_amount,
        ], 201);
    }
}