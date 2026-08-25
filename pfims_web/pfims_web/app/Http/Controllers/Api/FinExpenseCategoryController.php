<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

            return response()->json($categories->map(function ($item) {
                return [
                    'expense_category_id' => $item->fin_category_id,
                    'fin_category_id' => $item->fin_category_id,
                    'category_code' => $item->category_code,
                    'category_name' => $item->category_name,
                    'classification' => $item->classification,
                ];
            }));
        } catch (\Exception $e) {
            report($e);

            return response()->json(['message' => 'Finance categories could not be loaded.'], 500);
        }
    }
}
