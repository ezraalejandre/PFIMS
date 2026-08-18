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
            $categories = DB::table('fin_expense_category_tbl')
                ->select('fin_category_id', 'category_code', 'category_name', 'classification')
                ->where('is_active', 1)
                ->orderBy('category_name')
                ->get();
            
            if ($categories->isEmpty()) {
                return response()->json([
                    ['fin_category_id' => 1, 'category_code' => 'CONST_SUPPLY', 'category_name' => 'Construction Supply'],
                    ['fin_category_id' => 2, 'category_code' => 'SALARIES_WAGES', 'category_name' => 'Salaries & Wages'],
                    ['fin_category_id' => 3, 'category_code' => 'TRANSPO', 'category_name' => 'Transportation'],
                    ['fin_category_id' => 4, 'category_code' => 'UTILITIES', 'category_name' => 'Utilities'],
                    ['fin_category_id' => 5, 'category_code' => 'DELIVERY', 'category_name' => 'Delivery'],
                    ['fin_category_id' => 6, 'category_code' => 'OTHERS', 'category_name' => 'Others']
                ]);
            }
            
            return response()->json($categories->map(function($item) {
                return [
                    'expense_category_id' => $item->fin_category_id,
                    'fin_category_id' => $item->fin_category_id,
                    'category_code' => $item->category_code,
                    'category_name' => $item->category_name,
                    'classification' => $item->classification
                ];
            }));
        } catch (\Exception $e) {
            return response()->json([
                ['expense_category_id' => 1, 'category_code' => 'CONST_SUPPLY', 'category_name' => 'Construction Supply'],
                ['expense_category_id' => 2, 'category_code' => 'SALARIES_WAGES', 'category_name' => 'Salaries & Wages'],
                ['expense_category_id' => 3, 'category_code' => 'TRANSPO', 'category_name' => 'Transportation']
            ]);
        }
    }
}