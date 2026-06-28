<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\InventoryCategory;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    private array $map = [
        'units' => [
            'model' => Unit::class,
            'id' => 'unit_id',
            'name' => 'unit_name',
        ],
        'inv_categories' => [
            'model' => InventoryCategory::class,
            'id' => 'inventory_category_id',
            'name' => 'inventory_category_name',
        ],
        'exp_categories' => [
            'model' => ExpenseCategory::class,
            'id' => 'expense_category_id',
            'name' => 'category_name',
        ],
    ];

    public function index(string $type): JsonResponse
    {
        if (!isset($this->map[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid config type.'], 400);
        }

        $config = $this->map[$type];
        $model = $config['model'];
        $items = $model::orderBy($config['name'])->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        if (!isset($this->map[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid config type.'], 400);
        }

        $config = $this->map[$type];
        $nameKey = $config['name'];

        $validated = $request->validate([
            $nameKey => 'required|string|max:100',
        ]);

        $model = $config['model'];
        $item = $model::create([$nameKey => $validated[$nameKey]]);

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Item added successfully!'], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        if (!isset($this->map[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid config type.'], 400);
        }

        $config = $this->map[$type];
        $model = $config['model'];
        $nameKey = $config['name'];
        $idKey = $config['id'];

        $item = $model::findOrFail($id);

        $validated = $request->validate([
            $nameKey => 'required|string|max:100',
        ]);

        $item->$nameKey = $validated[$nameKey];
        $item->save();

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Item updated successfully!']);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        if (!isset($this->map[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid config type.'], 400);
        }

        $config = $this->map[$type];
        $model = $config['model'];

        $item = $model::findOrFail($id);
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Item deleted successfully!']);
    }
}
