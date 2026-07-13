<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MLService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            'timestamp' => now()->toDateTimeString()
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
                'model_exists' => file_exists(storage_path('app/ml_model.phpml'))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'MLService error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function predictProjectCost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'budget' => 'required|numeric|min:0',
            'duration' => 'required|numeric|min:1',
            'workers' => 'nullable|numeric|min:0',
            'completion' => 'nullable|numeric|min:0|max:100',
            'material_cost' => 'nullable|numeric|min:0',
            'labor_cost' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $prediction = $this->ml->predictProjectCost(
                $request->input('budget'),
                $request->input('duration'),
                $request->input('workers', 5),
                $request->input('completion', 0),
                $request->input('material_cost', 0),
                $request->input('labor_cost', 0)
            );
            
            $budget = $request->input('budget');
            $variance = $prediction - $budget;
            $variancePercentage = $budget > 0 ? ($variance / $budget) * 100 : 0;
            
            return response()->json([
                'success' => true,
                'predicted_cost' => round($prediction, 2),
                'formatted' => '₱' . number_format($prediction, 2),
                'variance' => round($variance, 2),
                'variance_percentage' => round($variancePercentage, 2),
                'status' => $variance > 0 ? 'Warning: Predicted cost exceeds budget' : 'On track',
                'input_features' => [
                    'budget' => $request->input('budget'),
                    'duration_months' => $request->input('duration'),
                    'worker_count' => $request->input('workers', 5),
                    'completion_percentage' => $request->input('completion', 0),
                    'material_cost' => $request->input('material_cost', 0),
                    'labor_cost' => $request->input('labor_cost', 0)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Prediction failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Prediction failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function predictMaterialDemand(Request $request)
    {
        try {
            $predictions = $this->ml->predictMaterialDemand();
            return response()->json([
                'success' => true,
                'predictions' => $predictions,
                'message' => 'Material demand forecast generated'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to predict material demand: ' . $e->getMessage()
            ], 500);
        }
    }

    public function retrain(Request $request)
    {
        try {
            $result = $this->ml->retrain();
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'metrics' => $result['metrics']
            ]);
        } catch (\Exception $e) {
            Log::error('Retraining failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Retraining failed: ' . $e->getMessage()
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
                'message' => 'Model status retrieved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get model status: ' . $e->getMessage()
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
                'message' => 'Budget variance analysis completed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze budget variance: ' . $e->getMessage()
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
                'material_status' => $materialStatus
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
                'project_status' => $projectStatus
            ];

            // ─── PREDICTIVE ANALYTICS ───
            $predictive = [
                'material_forecast' => $this->ml->predictMaterialDemand(),
                'cost_forecast' => $this->getCostForecast()
            ];

            // ─── MODEL METRICS ───
            $modelMetrics = $this->ml->getModelMetrics();

            return response()->json([
                'success' => true,
                'descriptive' => $descriptive,
                'diagnostic' => $diagnostic,
                'predictive' => $predictive,
                'model_metrics' => $modelMetrics
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard analytics failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get analytics: ' . $e->getMessage()
            ], 500);
        }
    }

        protected function getCostForecast()
    {
        return DB::table('expense_tbl')
            ->select(
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as month"),
                DB::raw('SUM(labor_amount + material_amount + equipment_amount + other_amount) as total')
            )
            ->whereNotNull('expense_date')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();
    }
}