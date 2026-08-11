<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinExpenseCategoryController extends Controller
{
    public function index()
    {
        try {
            // Try to get categories from database
            $categories = DB::table('fin_expense_category_tbl')
                ->select('fin_category_id', 'category_name')
                ->where('is_active', 1)
                ->orderBy('category_name')
                ->get();
            
            // If no categories found, return fallback
            if ($categories->isEmpty()) {
                return response()->json([
                    ['fin_category_id' => 1, 'category_name' => 'Construction Supply'],
                    ['fin_category_id' => 2, 'category_name' => 'Salaries & Wages'],
                    ['fin_category_id' => 3, 'category_name' => 'Transportation'],
                    ['fin_category_id' => 4, 'category_name' => 'Utilities'],
                    ['fin_category_id' => 5, 'category_name' => 'Delivery'],
                    ['fin_category_id' => 6, 'category_name' => 'Others']
                ]);
            }
            
            // Map to match frontend expectations
            return response()->json($categories->map(function($item) {
                return [
                    'expense_category_id' => $item->fin_category_id,
                    'fin_category_id' => $item->fin_category_id,
                    'category_name' => $item->category_name
                ];
            }));
        } catch (\Exception $e) {
            // Return fallback on any error
            return response()->json([
                ['expense_category_id' => 1, 'category_name' => 'Construction Supply'],
                ['expense_category_id' => 2, 'category_name' => 'Salaries & Wages'],
                ['expense_category_id' => 3, 'category_name' => 'Transportation']
            ]);
        }
    }
}