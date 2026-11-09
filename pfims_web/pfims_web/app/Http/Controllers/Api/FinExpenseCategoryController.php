<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

// Group the fin expense category actions.
class FinExpenseCategoryController extends Controller
{
    // List the fin expense category records.
    public function index()
    {
        // Try this action and handle any errors below.
        try {
            $categories = DB::table('fin_expense_category_tbl')
                ->select('fin_category_id', 'category_code', 'category_name', 'classification')
                ->where('is_active', 1)
                ->whereNotIn('category_code', ['ADMINISTRATIVE_EXPENSES', 'SSS_PHILHEALTH_CONSTBOND'])
                ->orderBy('category_name')
                ->get();

            // Build a result for each record.
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
