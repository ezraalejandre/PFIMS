<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Budget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch only projects that are NOT completed or pending
        // Order by project_id (or start_date if available)
        $projects = Project::where('status', '!=', 'Completed')
            ->where('status', '!=', 'Pending')
            ->orderBy('project_id', 'desc') // Changed from created_at to project_id
            ->get();

        // Get active projects count
        $activeProjects = $projects->count();

        // Calculate total budget
        $totalBudget = Budget::sum('budget_amount') ?? 0;
        $actualBudget = Budget::sum('actual_amount') ?? 0;
        $remainingBudget = $totalBudget - $actualBudget;

        // Get user info
        $user = Auth::user();

        // Get completion trend data (using start_date instead of created_at)
        $completionData = $this->getCompletionTrend();

        return view('dashboard', compact(
            'projects', 
            'activeProjects', 
            'totalBudget', 
            'remainingBudget',
            'user',
            'completionData'
        ));
    }

    private function getCompletionTrend()
    {
        $months = [];
        $percentages = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthName = $month->format('M');
            $months[] = $monthName;

            // Use start_date instead of created_at
            $avg = Project::whereMonth('start_date', $month->month)
                ->whereYear('start_date', $month->year)
                ->avg('completion_percentage') ?? 0;

            $percentages[] = round($avg * 0.9 + 10, 0);
        }

        return [
            'months' => $months,
            'percentages' => $percentages
        ];
    }
}