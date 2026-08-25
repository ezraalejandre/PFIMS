<?php

namespace App\Http\Controllers;

use App\Models\FinExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfigController extends Controller
{
    private array $map = [
        'units' => [
            'model' => Unit::class,
            'table' => 'unit_tbl',
            'id' => 'unit_id',
            'name' => 'unit_name',
            'fields' => [
                'unit_name' => ['label' => 'Unit name', 'type' => 'text', 'required' => true, 'max' => 100],
            ],
        ],
        'inv_categories' => [
            'model' => InventoryCategory::class,
            'table' => 'inventory_category_tbl',
            'id' => 'inventory_category_id',
            'name' => 'inventory_category_name',
            'fields' => [
                'inventory_category_name' => ['label' => 'Category name', 'type' => 'text', 'required' => true, 'max' => 100],
            ],
        ],
        'exp_categories' => [
            'model' => FinExpenseCategory::class,
            'table' => 'fin_expense_category_tbl',
            'id' => 'fin_category_id',
            'name' => 'category_name',
            'fields' => [
                'category_code' => ['label' => 'Category code', 'type' => 'text', 'required' => true, 'max' => 40],
                'category_name' => ['label' => 'Category name', 'type' => 'text', 'required' => true, 'max' => 100],
                'classification' => ['label' => 'Classification', 'type' => 'select', 'required' => true, 'options' => ['direct' => 'Direct', 'admin' => 'Administrative']],
                'is_active' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['1' => 'Active', '0' => 'Inactive']],
            ],
        ],
        'suppliers' => [
            'model' => Supplier::class,
            'table' => 'supplier_tbl',
            'id' => 'supplier_id',
            'name' => 'supplier_name',
            'fields' => [
                'supplier_name' => ['label' => 'Supplier name', 'type' => 'text', 'required' => true, 'max' => 100],
                'address' => ['label' => 'Address', 'type' => 'text', 'required' => true, 'max' => 255],
                'contact_number' => ['label' => 'Contact number', 'type' => 'text', 'required' => true, 'max' => 20],
            ],
        ],
    ];

    public function index(Request $request, string $type): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        $query = $model::query()->orderBy($config['name']);
        if ($request->filled('search')) {
            $query->where($config['name'], 'like', '%'.trim((string) $request->input('search')).'%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
            'meta' => $this->meta($config),
        ]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $validated = $this->normalize($type, $request->validate($this->rules($config)));
        $this->rejectDuplicate($config, $validated);
        $model = $config['model'];
        $item = $model::create($validated);

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Configuration added successfully.'], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        /** @var Model $item */
        $item = $model::findOrFail($id);
        $validated = $this->normalize($type, $request->validate($this->rules($config)));
        $this->rejectDuplicate($config, $validated, $id);
        $item->update($validated);

        return response()->json(['success' => true, 'data' => $item->fresh(), 'message' => 'Configuration updated successfully.']);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        /** @var Model $item */
        $item = $model::findOrFail($id);
        $dependency = match ($type) {
            'units' => DB::table('inventory_item_tbl')->where('unit_id', $id)->exists() ? 'inventory items' : null,
            'inv_categories' => DB::table('inventory_item_tbl')->where('inventory_category_id', $id)->exists() ? 'inventory items' : null,
            'exp_categories' => DB::table('fin_expense_tbl')->where('fin_category_id', $id)->exists() ? 'finance expenses' : null,
            'suppliers' => DB::table('inventory_item_tbl')->where('supplier_id', $id)->exists() ? 'inventory items' : null,
            default => null,
        };
        if ($dependency) {
            return response()->json(['success' => false, 'message' => "This configuration cannot be deleted because it is used by {$dependency}."], 409);
        }
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Configuration deleted successfully.']);
    }

    private function configuration(string $type): array
    {
        abort_unless(isset($this->map[$type]), 404, 'Configuration type not found.');

        return $this->map[$type];
    }

    private function rules(array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $field => $definition) {
            $fieldRules = [$definition['required'] ? 'required' : 'nullable'];
            if (($definition['type'] ?? 'text') === 'select') {
                $fieldRules[] = 'in:'.implode(',', array_keys($definition['options']));
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:'.$definition['max'];
            }
            if ($field === 'contact_number') {
                $fieldRules[] = 'regex:/^(?=.*\d)[0-9+().\s-]+$/';
            }
            if ($field === 'category_code') {
                $fieldRules[] = 'regex:/^[A-Za-z][A-Za-z0-9_ -]*$/';
            }
            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    private function normalize(string $type, array $data): array
    {
        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }
        if ($type === 'exp_categories') {
            $data['category_code'] = Str::upper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $data['category_code']));
            $data['is_active'] = (bool) $data['is_active'];
        }

        return $data;
    }

    private function rejectDuplicate(array $config, array $data, ?int $ignoreId = null): void
    {
        $fields = [$config['name']];
        if (isset($data['category_code'])) {
            $fields[] = 'category_code';
        }
        foreach ($fields as $field) {
            $query = DB::table($config['table'])->whereRaw("LOWER(TRIM({$field})) = ?", [Str::lower(trim((string) $data[$field]))]);
            if ($ignoreId !== null) {
                $query->where($config['id'], '!=', $ignoreId);
            }
            abort_if($query->exists(), 409, ucfirst(str_replace('_', ' ', $field)).' already exists.');
        }
    }

    private function meta(array $config): array
    {
        return ['id' => $config['id'], 'name' => $config['name'], 'fields' => $config['fields']];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->user()?->role) === 'admin', 403, 'Only administrators can manage configurations.');
    }
}
