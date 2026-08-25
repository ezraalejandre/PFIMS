<?php

namespace App\Http\Controllers;

use App\Services\MLService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MLController extends Controller
{
    protected $ml;

    public function __construct(MLService $ml)
    {
        $this->ml = $ml;
    }

    public function test()
    {
        return response()->json([
            'success' => true,
            'message' => 'ML Controller is working!',
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function testService()
    {
        try {
            $metrics = $this->ml->getModelMetrics();

            return response()->json([
                'success' => true,
                'message' => 'MLService is working!',
                'metrics' => $metrics,
                'model_exists' => file_exists(storage_path('app/ml_model.phpml')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'MLService error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function predictProjectCost(Request $request)
    {
        $selectedProject = null;
        if ($request->filled('project_id')) {
            $selectedProject = $this->ml->getPredictionProjects()
                ->firstWhere('project_id', (int) $request->input('project_id'));
            if ($selectedProject === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected project is not eligible for prediction. Confirm that it has a budget, schedule, workforce, and incomplete status.',
                    'errors' => ['project_id' => ['Select an eligible project.']],
                ], 422);
            }
        }

        $input = [
            'budget' => $selectedProject['budget'] ?? $request->input('budget'),
            'duration' => $selectedProject['duration'] ?? $request->input('duration'),
            'workers' => $selectedProject['workers'] ?? ($request->filled('workers') ? $request->input('workers') : 5),
            'completion' => $selectedProject['completion'] ?? ($request->filled('completion') ? $request->input('completion') : 0),
            'material_cost' => $selectedProject['material_cost'] ?? ($request->filled('material_cost') ? $request->input('material_cost') : 0),
            'labor_cost' => $selectedProject['labor_cost'] ?? ($request->filled('labor_cost') ? $request->input('labor_cost') : 0),
            'fin_total_expense' => $selectedProject['fin_total_expense'] ?? ($request->filled('fin_total_expense') ? $request->input('fin_total_expense') : 0),
            'fin_material_expense' => $selectedProject['fin_material_expense'] ?? ($request->filled('fin_material_expense') ? $request->input('fin_material_expense') : 0),
            'fin_labor_expense' => $selectedProject['fin_labor_expense'] ?? ($request->filled('fin_labor_expense') ? $request->input('fin_labor_expense') : 0),
            'fin_equipment_expense' => $selectedProject['fin_equipment_expense'] ?? ($request->filled('fin_equipment_expense') ? $request->input('fin_equipment_expense') : 0),
            'fin_other_expense' => $selectedProject['fin_other_expense'] ?? ($request->filled('fin_other_expense') ? $request->input('fin_other_expense') : 0),
            'finance_as_of_date' => $selectedProject['finance_as_of_date'] ?? $request->input('finance_as_of_date'),
        ];

        $validator = Validator::make($input, [
            'budget' => ['bail', 'required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'duration' => ['bail', 'required', 'integer', 'between:1,600'],
            'workers' => ['bail', 'required', 'integer', 'between:1,100000'],
            'completion' => ['bail', 'required', 'numeric', 'between:0,100'],
            'material_cost' => ['bail', 'required', 'numeric', 'between:0,9999999999.99'],
            'labor_cost' => ['bail', 'required', 'numeric', 'between:0,9999999999.99'],
            'fin_total_expense' => ['bail', 'required', 'numeric', 'between:0,9999999999.99'],
            'fin_material_expense' => ['bail', 'required', 'numeric', 'between:0,9999999999.99'],
            'fin_labor_expense' => ['bail', 'required', 'numeric', 'between:0,9999999999.99'],
            'fin_equipment_expense' => ['bail', 'required', 'numeric', 'between:0,9999999999.99'],
            'fin_other_expense' => ['bail', 'required', 'numeric', 'between:0,9999999999.99'],
            'finance_as_of_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $hasFinanceInputs = collect([
            $input['fin_total_expense'],
            $input['fin_material_expense'],
            $input['fin_labor_expense'],
            $input['fin_equipment_expense'],
            $input['fin_other_expense'],
        ])->contains(fn ($value) => is_numeric($value) && (float) $value > 0);
        $validator->after(function ($validator) use ($hasFinanceInputs, $input) {
            if ($hasFinanceInputs && blank($input['finance_as_of_date'])) {
                $validator->errors()->add('finance_as_of_date', 'Provide the date through which the finance totals have accumulated.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the invalid prediction inputs.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $prediction = $this->ml->predictProjectCost(
                $validated['budget'],
                $validated['duration'],
                $validated['workers'],
                $validated['completion'],
                $validated['material_cost'],
                $validated['labor_cost'],
                $validated['fin_total_expense'],
                $validated['fin_material_expense'],
                $validated['fin_labor_expense'],
                $validated['fin_equipment_expense'],
                $validated['fin_other_expense']
            );

            $budget = (float) $validated['budget'];
            $variance = $prediction - $budget;
            $variancePercentage = $budget > 0 ? ($variance / $budget) * 100 : 0;
            $riskLevel = match (true) {
                $variancePercentage <= 0 => 'On track',
                $variancePercentage <= 2 => 'Low risk',
                $variancePercentage <= 5 => 'Moderate risk',
                $variancePercentage <= 10 => 'High risk',
                default => 'Critical risk',
            };
            $modelMetrics = $this->ml->getModelMetrics();

            return response()->json([
                'success' => true,
                'predicted_cost' => round($prediction, 2),
                'formatted' => '₱'.number_format($prediction, 2),
                'variance' => round($variance, 2),
                'variance_percentage' => round($variancePercentage, 2),
                'status' => $variance > 0 ? 'Warning: Predicted cost exceeds budget' : 'On track',
                'risk_level' => $riskLevel,
                'business_action' => $this->ml->businessActionForRiskLevel($riskLevel),
                'prediction_source' => $this->ml->getLastPredictionSource(),
                'model_accuracy' => $modelMetrics['accuracy'] ?? null,
                'model_accuracy_scope' => $modelMetrics['metric_scope'] ?? 'unavailable',
                'warnings' => $this->ml->getLastPredictionWarnings(),
                'input_features' => [
                    'project_id' => $selectedProject['project_id'] ?? null,
                    'project_name' => $selectedProject['project_name'] ?? null,
                    'budget' => (float) $validated['budget'],
                    'duration_months' => (int) $validated['duration'],
                    'worker_count' => (int) $validated['workers'],
                    'completion_percentage' => (float) $validated['completion'],
                    'material_cost' => (float) $validated['material_cost'],
                    'labor_cost' => (float) $validated['labor_cost'],
                    'fin_total_expense' => (float) $validated['fin_total_expense'],
                    'fin_material_expense' => (float) $validated['fin_material_expense'],
                    'fin_labor_expense' => (float) $validated['fin_labor_expense'],
                    'fin_equipment_expense' => (float) $validated['fin_equipment_expense'],
                    'fin_other_expense' => (float) $validated['fin_other_expense'],
                    'finance_as_of_date' => $validated['finance_as_of_date'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Prediction failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Prediction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function predictionProjects()
    {
        return response()->json([
            'success' => true,
            'projects' => $this->ml->getPredictionProjects(),
        ]);
    }

    public function predictMaterialDemand(Request $request)
    {
        try {
            $predictions = $this->ml->predictMaterialDemand();

            return response()->json([
                'success' => true,
                'predictions' => $predictions,
                'forecast_horizon_days' => 30,
                'message' => '30-day material stock projection generated',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to predict material demand: '.$e->getMessage(),
            ], 500);
        }
    }

    public function retrain(Request $request)
    {
        if (! $request->user() || strtolower((string) $request->user()->role) !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators may retrain the ML model.',
            ], 403);
        }

        try {
            $result = $this->ml->retrain();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'model_source' => $result['model_source'],
                'metrics' => $result['metrics'],
            ]);
        } catch (\Exception $e) {
            Log::error('Retraining failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Retraining failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function status()
    {
        try {
            $metrics = $this->ml->getModelMetrics();

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'message' => 'Model status retrieved',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get model status: '.$e->getMessage(),
            ], 500);
        }
    }

    public function budgetVariance()
    {
        try {
            $analysis = $this->ml->analyzeBudgetVariance();

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'message' => 'Budget variance analysis completed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze budget variance: '.$e->getMessage(),
            ], 500);
        }
    }

    public function dashboardAnalytics()
    {
        try {
            // ─── DESCRIPTIVE ANALYTICS ───
            $totalProjects = DB::table('project_tbl')->count();
            $activeProjects = DB::table('project_tbl')->where('status', '!=', 'Completed')->count();
            $completedProjects = DB::table('project_tbl')->where('status', 'Completed')->count();

            // Get total budget from budgets_tbl
            $totalBudget = DB::table('budgets_tbl')->sum('budget_amount') ?? 0;
            $totalExpenses = DB::table('budgets_tbl')->sum('actual_amount') ?? 0;
            $totalItems = DB::table('inventory_item_tbl')->count();
            $totalSuppliers = DB::table('supplier_tbl')->count();

            // Material status
            $materialStatus = DB::table('inventory_item_tbl')
                ->select(
                    'item_name',
                    'current_stock',
                    'reorder_level',
                    DB::raw('CASE 
                        WHEN current_stock <= reorder_level THEN "Reorder Needed"
                        WHEN current_stock <= reorder_level * 1.5 THEN "Low Stock"
                        ELSE "Sufficient"
                    END as status')
                )
                ->limit(20)
                ->get();

            $descriptive = [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'completed_projects' => $completedProjects,
                'total_inventory_items' => $totalItems,
                'total_suppliers' => $totalSuppliers,
                'total_budget' => $totalBudget,
                'total_expenses' => $totalExpenses,
                'material_status' => $materialStatus,
            ];

            // ─── DIAGNOSTIC ANALYTICS ───
            $budgetVariance = $this->ml->analyzeBudgetVariance();

            $projectStatus = DB::table('project_tbl')
                ->select('project_name', 'status', 'completion_percentage', 'start_date', 'estimated_end_date')
                ->limit(20)
                ->get();

            $diagnostic = [
                'budget_variance' => $budgetVariance,
                'material_status' => $materialStatus,
                'project_status' => $projectStatus,
            ];

            // ─── PREDICTIVE ANALYTICS ───
            $monthlyExpenseHistory = $this->getMonthlyExpenseHistory();
            $predictive = [
                'material_forecast' => $this->ml->predictMaterialDemand(),
                // Compatibility alias retained; these values are historical, not predictions.
                'cost_forecast' => $monthlyExpenseHistory,
                'monthly_expense_history' => $monthlyExpenseHistory,
                'cost_series_type' => 'historical_monthly_expenses',
            ];

            // ─── MODEL METRICS ───
            $modelMetrics = $this->ml->getModelMetrics();

            return response()->json([
                'success' => true,
                'descriptive' => $descriptive,
                'diagnostic' => $diagnostic,
                'predictive' => $predictive,
                'model_metrics' => $modelMetrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard analytics failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get analytics: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function getMonthlyExpenseHistory()
    {
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', expense_date)"
            : "DATE_FORMAT(expense_date, '%Y-%m')";

        return DB::table('fin_expense_tbl')
            ->select(
                DB::raw("{$monthExpression} as month"),
                DB::raw('SUM(amount) as total')
            )
            ->whereNotNull('expense_date')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();
    }
}
