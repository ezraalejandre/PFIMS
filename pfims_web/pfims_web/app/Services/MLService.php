<?php

namespace App\Services;

use Phpml\Regression\LeastSquares;
use Phpml\ModelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MLService
{
    protected $model;
    protected $modelPath;

    public function __construct()
    {
        $this->modelPath = storage_path('app/ml_model.phpml');
        $this->loadOrTrainModel();
    }

    protected function loadOrTrainModel()
    {
        $manager = new ModelManager();
        if (file_exists($this->modelPath)) {
            try {
                $this->model = $manager->restoreFromFile($this->modelPath);
                Log::info('ML Model loaded successfully');
                return;
            } catch (\Exception $e) {
                Log::warning('Failed to load ML model: ' . $e->getMessage());
            }
        }
        $this->train();
    }

    public function train()
    {
        try {
            $data = $this->getTrainingData();
            
            if ($data->isEmpty()) {
                Log::warning('No training data available, using fallback data');
                $data = $this->getFallbackData();
            }

            if ($data->isEmpty()) {
                throw new \Exception('No training data available');
            }

            $samples = [];
            $labels = [];

            foreach ($data as $row) {
                // Add small random noise to prevent singular matrix
                $budget = (float) ($row->budget ?? 0) * (1 + (rand(-100, 100) / 10000));
                $duration = (float) ($row->duration_months ?? 6) * (1 + (rand(-100, 100) / 10000));
                $workers = (float) ($row->worker_count ?? 5) + (rand(-5, 5) / 10);
                $completion = (float) ($row->completion_percentage ?? 0);
                $material = (float) ($row->material_cost ?? 0) * (1 + (rand(-100, 100) / 10000));
                $labor = (float) ($row->labor_cost ?? 0) * (1 + (rand(-100, 100) / 10000));
                
                $samples[] = [
                    max(0, $budget),
                    max(1, $duration),
                    max(1, $workers),
                    min(100, max(0, $completion)),
                    max(0, $material),
                    max(0, $labor)
                ];
                $labels[] = max(0, (float) ($row->actual_cost ?? 0) * (1 + (rand(-100, 100) / 10000)));
            }

            // Filter valid samples
            $filteredSamples = [];
            $filteredLabels = [];
            foreach ($samples as $index => $sample) {
                if ($sample[0] > 0 && $sample[1] > 0 && $labels[$index] > 0) {
                    $filteredSamples[] = $sample;
                    $filteredLabels[] = $labels[$index];
                }
            }

            if (count($filteredSamples) < 3) {
                $fallbackData = $this->getFallbackData();
                foreach ($fallbackData as $row) {
                    $filteredSamples[] = [
                        (float) $row->budget,
                        (float) $row->duration_months,
                        (float) $row->worker_count,
                        (float) $row->completion_percentage,
                        (float) $row->material_cost,
                        (float) $row->labor_cost
                    ];
                    $filteredLabels[] = (float) $row->actual_cost;
                }
            }

            $this->model = new LeastSquares();
            $this->model->train($filteredSamples, $filteredLabels);

            $manager = new ModelManager();
            $manager->saveToFile($this->model, $this->modelPath);
            
            Log::info('ML Model trained successfully with ' . count($filteredSamples) . ' samples');
            return true;
        } catch (\Exception $e) {
            Log::error('ML Training failed: ' . $e->getMessage());
            $this->createFallbackModel();
            return false;
        }
    }

    protected function createFallbackModel()
    {
        $samples = [
            [1000000, 6, 10, 100, 600000, 350000],
            [2000000, 8, 15, 100, 1200000, 600000],
            [500000, 3, 5, 100, 300000, 150000],
            [3000000, 12, 20, 100, 1800000, 1000000],
            [1500000, 5, 8, 100, 900000, 500000],
            [800000, 4, 6, 100, 480000, 280000],
            [2500000, 10, 18, 100, 1500000, 800000],
            [4000000, 14, 25, 100, 2400000, 1300000],
        ];
        
        $labels = [
            950000, 1800000, 450000, 2800000, 1400000, 760000, 2300000, 3700000
        ];

        $this->model = new LeastSquares();
        $this->model->train($samples, $labels);

        $manager = new ModelManager();
        $manager->saveToFile($this->model, $this->modelPath);
        
        Log::info('Fallback model created successfully');
    }

    /**
     * Get training data - FIXED: Calculate actual_cost from expense columns
     */
    protected function getTrainingData()
    {
        try {
            // Get data from budgets_tbl and project_tbl
            $data = DB::table('project_tbl')
                ->join('budgets_tbl', 'project_tbl.project_id', '=', 'budgets_tbl.project_id')
                ->leftJoin('expense_tbl', 'project_tbl.project_id', '=', 'expense_tbl.project_id')
                ->select(
                    'project_tbl.project_id',
                    'project_tbl.project_name',
                    'budgets_tbl.budget_amount as budget',
                    'budgets_tbl.actual_amount as actual_cost',
                    'project_tbl.worker_count',
                    'project_tbl.completion_percentage',
                    DB::raw('TIMESTAMPDIFF(MONTH, project_tbl.start_date, COALESCE(project_tbl.actual_end_date, project_tbl.estimated_end_date, NOW())) as duration_months'),
                    // Calculate material cost from expense_tbl (category_id = 4 is Materials)
                    DB::raw('COALESCE(SUM(CASE WHEN expense_tbl.expense_category_id = 4 THEN expense_tbl.material_amount ELSE 0 END), 0) as material_cost'),
                    // Calculate labor cost from expense_tbl (category_id = 1 is Labor)
                    DB::raw('COALESCE(SUM(CASE WHEN expense_tbl.expense_category_id = 1 THEN expense_tbl.labor_amount ELSE 0 END), 0) as labor_cost')
                )
                ->whereIn('project_tbl.status', ['Completed', 'On Track', 'Delayed'])
                ->whereNotNull('budgets_tbl.budget_amount')
                ->where('budgets_tbl.budget_amount', '>', 0)
                ->whereNotNull('budgets_tbl.actual_amount')
                ->where('budgets_tbl.actual_amount', '>', 0)
                ->groupBy(
                    'project_tbl.project_id',
                    'project_tbl.project_name',
                    'budgets_tbl.budget_amount',
                    'budgets_tbl.actual_amount',
                    'project_tbl.worker_count',
                    'project_tbl.completion_percentage',
                    'project_tbl.start_date',
                    'project_tbl.estimated_end_date',
                    'project_tbl.actual_end_date'
                )
                ->limit(500)
                ->get();

            // If no data from budgets_tbl, try using expense_tbl only
            if ($data->isEmpty()) {
                $data = DB::table('project_tbl')
                    ->leftJoin('expense_tbl', 'project_tbl.project_id', '=', 'expense_tbl.project_id')
                    ->select(
                        'project_tbl.project_id',
                        'project_tbl.project_name',
                        DB::raw('COALESCE(SUM(expense_tbl.labor_amount + expense_tbl.material_amount + expense_tbl.equipment_amount + expense_tbl.other_amount), 0) as actual_cost'),
                        DB::raw('COALESCE(SUM(expense_tbl.labor_amount + expense_tbl.material_amount + expense_tbl.equipment_amount + expense_tbl.other_amount), 0) as budget'),
                        'project_tbl.worker_count',
                        'project_tbl.completion_percentage',
                        DB::raw('TIMESTAMPDIFF(MONTH, project_tbl.start_date, COALESCE(project_tbl.actual_end_date, project_tbl.estimated_end_date, NOW())) as duration_months'),
                        DB::raw('COALESCE(SUM(expense_tbl.material_amount), 0) as material_cost'),
                        DB::raw('COALESCE(SUM(expense_tbl.labor_amount), 0) as labor_cost')
                    )
                    ->whereIn('project_tbl.status', ['Completed', 'On Track', 'Delayed'])
                    ->groupBy(
                        'project_tbl.project_id',
                        'project_tbl.project_name',
                        'project_tbl.worker_count',
                        'project_tbl.completion_percentage',
                        'project_tbl.start_date',
                        'project_tbl.estimated_end_date',
                        'project_tbl.actual_end_date'
                    )
                    ->having('actual_cost', '>', 0)
                    ->limit(500)
                    ->get();
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Error getting training data: ' . $e->getMessage());
            return collect([]);
        }
    }

    protected function getFallbackData()
    {
        return collect([
            (object) ['budget' => 1000000, 'duration_months' => 6, 'worker_count' => 10, 'completion_percentage' => 100, 'material_cost' => 600000, 'labor_cost' => 350000, 'actual_cost' => 950000],
            (object) ['budget' => 2000000, 'duration_months' => 8, 'worker_count' => 15, 'completion_percentage' => 100, 'material_cost' => 1200000, 'labor_cost' => 600000, 'actual_cost' => 1800000],
            (object) ['budget' => 500000, 'duration_months' => 3, 'worker_count' => 5, 'completion_percentage' => 100, 'material_cost' => 300000, 'labor_cost' => 150000, 'actual_cost' => 450000],
            (object) ['budget' => 3000000, 'duration_months' => 12, 'worker_count' => 20, 'completion_percentage' => 100, 'material_cost' => 1800000, 'labor_cost' => 1000000, 'actual_cost' => 2800000],
            (object) ['budget' => 1500000, 'duration_months' => 5, 'worker_count' => 8, 'completion_percentage' => 100, 'material_cost' => 900000, 'labor_cost' => 500000, 'actual_cost' => 1400000],
            (object) ['budget' => 800000, 'duration_months' => 4, 'worker_count' => 6, 'completion_percentage' => 100, 'material_cost' => 480000, 'labor_cost' => 280000, 'actual_cost' => 760000],
            (object) ['budget' => 2500000, 'duration_months' => 10, 'worker_count' => 18, 'completion_percentage' => 100, 'material_cost' => 1500000, 'labor_cost' => 800000, 'actual_cost' => 2300000],
            (object) ['budget' => 4000000, 'duration_months' => 14, 'worker_count' => 25, 'completion_percentage' => 100, 'material_cost' => 2400000, 'labor_cost' => 1300000, 'actual_cost' => 3700000],
        ]);
    }

    public function predict(array $features)
    {
        if (!$this->model) {
            throw new \Exception('Model not trained. Please train the model first.');
        }
        try {
            return $this->model->predict($features);
        } catch (\Exception $e) {
            Log::error('Prediction failed: ' . $e->getMessage());
            return $this->fallbackPrediction($features);
        }
    }

    protected function fallbackPrediction($features)
    {
        $budget = $features[0] ?? 0;
        $duration = $features[1] ?? 6;
        $workers = $features[2] ?? 5;
        $materialCost = $features[4] ?? 0;
        $laborCost = $features[5] ?? 0;
        
        $prediction = ($budget * 0.7) + ($duration * 5000) + ($workers * 1000) + ($materialCost * 0.5) + ($laborCost * 0.5);
        return max($prediction, $budget * 0.5);
    }

    public function predictProjectCost($budget, $durationMonths, $workerCount = 5, 
                                       $completionPercentage = 0, $materialCost = 0, $laborCost = 0)
    {
        return $this->predict([
            (float) $budget, 
            (float) $durationMonths, 
            (float) $workerCount, 
            (float) $completionPercentage, 
            (float) $materialCost, 
            (float) $laborCost
        ]);
    }

    public function predictMaterialDemand()
    {
        try {
            return DB::table('inventory_item_tbl')
                ->leftJoin('inventory_transaction_tbl', 'inventory_item_tbl.item_id', '=', 'inventory_transaction_tbl.item_id')
                ->select(
                    'inventory_item_tbl.item_id',
                    'inventory_item_tbl.item_name',
                    'inventory_item_tbl.current_stock',
                    'inventory_item_tbl.reorder_level',
                    DB::raw('COALESCE(SUM(inventory_transaction_tbl.quantity), 0) as total_used'),
                    DB::raw('COALESCE(AVG(inventory_transaction_tbl.quantity), 0) as avg_usage'),
                    DB::raw('COUNT(DISTINCT inventory_transaction_tbl.project_id) as project_count')
                )
                ->where('inventory_transaction_tbl.transaction_type', 'OUT')
                ->groupBy('inventory_item_tbl.item_id', 'inventory_item_tbl.item_name', 
                         'inventory_item_tbl.current_stock', 'inventory_item_tbl.reorder_level')
                ->get()
                ->map(function ($item) {
                    $projectedDemand = $item->avg_usage * 1.2;
                    $status = 'Sufficient';
                    if ($item->current_stock <= $item->reorder_level) {
                        $status = 'Reorder Needed';
                    } elseif ($item->current_stock <= $projectedDemand * 2) {
                        $status = 'Low Stock';
                    }

                    return [
                        'item_name' => $item->item_name ?? 'Unknown',
                        'current_stock' => (float) ($item->current_stock ?? 0),
                        'avg_usage' => round((float) $item->avg_usage, 2),
                        'projected_demand' => round($projectedDemand, 2),
                        'reorder_level' => (float) ($item->reorder_level ?? 0),
                        'total_used' => (float) ($item->total_used ?? 0),
                        'project_count' => (int) ($item->project_count ?? 0),
                        'status' => $status,
                        'recommendation' => $status === 'Reorder Needed' ? 'Place order immediately' : 
                                           ($status === 'Low Stock' ? 'Monitor stock closely' : 'Stock is sufficient')
                    ];
                })
                ->keyBy('item_id');
        } catch (\Exception $e) {
            Log::error('Material demand prediction failed: ' . $e->getMessage());
            return collect([]);
        }
    }

    public function getModelMetrics()
    {
        if (!$this->model) {
            return [
                'status' => 'Model not trained',
                'samples_trained' => 0,
                'accuracy' => 0,
                'mean_absolute_error' => 0,
                'mean_absolute_percentage_error' => 0,
                'r_squared' => 0,
                'precision' => 0,
                'recall' => 0,
                'f1_score' => 0,
                'mae_formatted' => '₱0',
                'interpretation' => 'Train the model first'
            ];
        }

        $data = $this->getTrainingData();
        if ($data->isEmpty()) {
            $data = $this->getFallbackData();
        }

        if ($data->isEmpty()) {
            return [
                'status' => 'No data available',
                'samples_trained' => 0,
                'accuracy' => 0,
                'mean_absolute_error' => 0,
                'mean_absolute_percentage_error' => 0,
                'r_squared' => 0,
                'precision' => 0,
                'recall' => 0,
                'f1_score' => 0,
                'mae_formatted' => '₱0',
                'interpretation' => 'No training data available'
            ];
        }

        $predictions = [];
        $actuals = [];
        
        foreach ($data as $row) {
            $features = [
                (float) ($row->budget ?? 0),
                (float) ($row->duration_months ?? 6),
                (float) ($row->worker_count ?? 5),
                (float) ($row->completion_percentage ?? 0),
                (float) ($row->material_cost ?? 0),
                (float) ($row->labor_cost ?? 0)
            ];
            
            try {
                $predictions[] = $this->predict($features);
                $actuals[] = (float) ($row->actual_cost ?? 0);
            } catch (\Exception $e) {
                continue;
            }
        }

        if (empty($predictions)) {
            return [
                'status' => 'No valid predictions',
                'samples_trained' => count($data),
                'accuracy' => 0,
                'mean_absolute_error' => 0,
                'mean_absolute_percentage_error' => 0,
                'r_squared' => 0,
                'precision' => 0,
                'recall' => 0,
                'f1_score' => 0,
                'mae_formatted' => '₱0',
                'interpretation' => 'Unable to evaluate model'
            ];
        }

        $totalError = 0;
        foreach ($predictions as $index => $prediction) {
            $totalError += abs($prediction - $actuals[$index]);
        }
        $mae = $totalError / count($predictions);
        
        $mape = 0;
        $validPairs = 0;
        foreach ($predictions as $index => $prediction) {
            if ($actuals[$index] != 0) {
                $mape += abs(($actuals[$index] - $prediction) / $actuals[$index]);
                $validPairs++;
            }
        }
        $mape = $validPairs > 0 ? ($mape / $validPairs) * 100 : 0;
        $accuracy = max(0, 100 - $mape);

        $meanActual = array_sum($actuals) / count($actuals);
        $totalSumSquares = 0;
        $residualSumSquares = 0;
        foreach ($actuals as $index => $actual) {
            $totalSumSquares += pow($actual - $meanActual, 2);
            $residualSumSquares += pow($actual - $predictions[$index], 2);
        }
        $rSquared = $totalSumSquares > 0 ? 1 - ($residualSumSquares / $totalSumSquares) : 0;

        return [
            'status' => 'Model is trained',
            'samples_trained' => count($data),
            'accuracy' => round($accuracy, 2),
            'mean_absolute_error' => round($mae, 2),
            'mean_absolute_percentage_error' => round($mape, 2),
            'r_squared' => round($rSquared, 4),
            'precision' => 0,
            'recall' => 0,
            'f1_score' => 0,
            'mae_formatted' => '₱' . number_format($mae, 2),
            'interpretation' => $this->getInterpretation($accuracy, $mae, $rSquared)
        ];
    }

    protected function getInterpretation($accuracy, $mae, $rSquared)
    {
        if ($accuracy > 90 && $mae < 10000 && $rSquared > 0.8) {
            return 'Excellent predictive performance. Model is highly accurate.';
        } elseif ($accuracy > 80 && $mae < 50000 && $rSquared > 0.6) {
            return 'Good predictive performance. Model provides reliable estimates.';
        } elseif ($accuracy > 70) {
            return 'Moderate predictive performance. More data may improve accuracy.';
        } else {
            return 'Model needs improvement. Consider collecting more training data.';
        }
    }

    public function retrain()
    {
        $this->train();
        return [
            'message' => 'Model retrained successfully',
            'metrics' => $this->getModelMetrics()
        ];
    }

    /**
     * Analyze budget variance - FIXED: Calculate actual from expense columns
     */
    public function analyzeBudgetVariance()
    {
        try {
            return DB::table('project_tbl')
                ->join('budgets_tbl', 'project_tbl.project_id', '=', 'budgets_tbl.project_id')
                ->leftJoin('expense_tbl', 'project_tbl.project_id', '=', 'expense_tbl.project_id')
                ->select(
                    'project_tbl.project_id',
                    'project_tbl.project_name',
                    'budgets_tbl.budget_amount as budget',
                    'project_tbl.status',
                    DB::raw('COALESCE(SUM(expense_tbl.labor_amount + expense_tbl.material_amount + expense_tbl.equipment_amount + expense_tbl.other_amount), budgets_tbl.actual_amount, 0) as actual_cost'),
                    DB::raw('budgets_tbl.budget_amount - COALESCE(SUM(expense_tbl.labor_amount + expense_tbl.material_amount + expense_tbl.equipment_amount + expense_tbl.other_amount), budgets_tbl.actual_amount, 0) as variance'),
                    DB::raw('CASE 
                        WHEN budgets_tbl.budget_amount > 0 THEN (budgets_tbl.budget_amount - COALESCE(SUM(expense_tbl.labor_amount + expense_tbl.material_amount + expense_tbl.equipment_amount + expense_tbl.other_amount), budgets_tbl.actual_amount, 0)) / budgets_tbl.budget_amount * 100 
                        ELSE 0 
                    END as variance_percentage')
                )
                ->groupBy(
                    'project_tbl.project_id',
                    'project_tbl.project_name',
                    'budgets_tbl.budget_amount',
                    'project_tbl.status',
                    'budgets_tbl.actual_amount'
                )
                ->get();
        } catch (\Exception $e) {
            Log::error('Budget variance analysis failed: ' . $e->getMessage());
            return collect([]);
        }
    }
}