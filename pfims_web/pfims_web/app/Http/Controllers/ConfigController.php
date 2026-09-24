<?php

namespace App\Http\Controllers;

use App\Models\FinExpenseCategory;
use App\Models\FinanceComponent;
use App\Models\InventoryCategory;
use App\Models\ProjectPhase;
use App\Models\Unit;
use App\Services\ProjectPhaseProgressService;
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
        'project_phases' => [
            'model' => ProjectPhase::class,
            'table' => 'project_phase_tbl',
            'id' => 'phase_id',
            'name' => 'phase_name',
            'fields' => [
                'phase_name' => ['label' => 'Project phase', 'type' => 'text', 'required' => true, 'max' => 100],
                'stage_order' => ['label' => 'Stage', 'type' => 'number', 'required' => true, 'min' => 1, 'step' => 1],
            ],
        ],
        'finance_components' => [
            'model' => FinanceComponent::class,
            'table' => 'fin_component_tbl',
            'id' => 'component_id',
            'name' => 'component_name',
            'fields' => [
                'component_name' => ['label' => 'Finance component', 'type' => 'text', 'required' => true, 'max' => 100],
            ],
        ],
    ];

    public function __construct(private ProjectPhaseProgressService $phaseProgress) {}

    public function index(Request $request, string $type): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        $query = $model::query();
        if ($type === 'project_phases') {
            $query->orderBy('stage_order')->orderBy('phase_id');
        } else {
            $query->orderBy($config['name']);
        }
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
        $validated = $this->normalize($type, $request->validate($this->rules($config), [
            'category_code.regex' => 'Category code must start with a letter and use only letters, numbers, spaces, hyphens, or underscores.',
        ]));
        $this->rejectDuplicate($config, $validated);
        $model = $config['model'];
        $item = DB::transaction(function () use ($type, $validated, $model) {
            if ($type !== 'project_phases') {
                return $model::create($validated);
            }

            $stage = min((int) $validated['stage_order'], ProjectPhase::query()->count() + 1);
            ProjectPhase::query()->where('stage_order', '>=', $stage)->increment('stage_order');
            $validated['stage_order'] = $stage;
            $item = $model::create($validated);
            $this->phaseProgress->normalizeStageOrder();
            $this->phaseProgress->syncAllProjects();
            return $item;
        });

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Configuration added successfully.'], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        /** @var Model $item */
        $item = $model::findOrFail($id);
        $validated = $this->normalize($type, $request->validate($this->rules($config), [
            'category_code.regex' => 'Category code must start with a letter and use only letters, numbers, spaces, hyphens, or underscores.',
        ]));
        $this->rejectDuplicate($config, $validated, $id);
        DB::transaction(function () use ($type, $item, $validated) {
            if ($type !== 'project_phases') {
                $item->update($validated);
                return;
            }

            $oldName = (string) $item->phase_name;
            $oldStage = (int) $item->stage_order;
            $newStage = min((int) $validated['stage_order'], ProjectPhase::query()->count());

            if ($newStage < $oldStage) {
                ProjectPhase::query()->where($item->getKeyName(), '!=', $item->getKey())
                    ->whereBetween('stage_order', [$newStage, $oldStage - 1])->increment('stage_order');
            } elseif ($newStage > $oldStage) {
                ProjectPhase::query()->where($item->getKeyName(), '!=', $item->getKey())
                    ->whereBetween('stage_order', [$oldStage + 1, $newStage])->decrement('stage_order');
            }

            $validated['stage_order'] = $newStage;
            $item->update($validated);
            if (trim($oldName) !== trim((string) $item->phase_name)) {
                DB::table('project_tbl')->whereRaw('LOWER(TRIM(phase)) = ?', [Str::lower(trim($oldName))])
                    ->update(['phase' => $item->phase_name]);
            }
            $this->phaseProgress->normalizeStageOrder();
            $this->phaseProgress->syncAllProjects();
        });

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
            'project_phases' => DB::table('project_tbl')->whereRaw('LOWER(TRIM(phase)) = ?', [Str::lower(trim((string) $item->phase_name))])->exists() ? 'projects' : null,
            'finance_components' => DB::table('fin_expense_tbl')->whereRaw('LOWER(TRIM(project_cost_component)) = ?', [Str::lower(trim((string) $item->component_name))])->exists() ? 'finance expenses' : null,
            default => null,
        };
        if ($dependency) {
            return response()->json(['success' => false, 'message' => "This configuration cannot be deleted because it is used by {$dependency}."], 409);
        }
        DB::transaction(function () use ($type, $item) {
            $item->delete();
            if ($type === 'project_phases') {
                $this->phaseProgress->normalizeStageOrder();
                $this->phaseProgress->syncAllProjects();
            }
        });

        return response()->json(['success' => true, 'message' => 'Configuration deleted successfully.']);
    }

    private function configuration(string $type): array
    {
        abort_unless(isset($this->map[$type]), 404, 'Configuration type not found.');
        if ($type === 'project_phases') {
            $this->phaseProgress->ensureSchema();
        }

        return $this->map[$type];
    }

    private function rules(array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $field => $definition) {
            $fieldRules = [$definition['required'] ? 'required' : 'nullable'];
            if (($definition['type'] ?? 'text') === 'select') {
                $fieldRules[] = 'in:'.implode(',', array_keys($definition['options']));
            } elseif (($definition['type'] ?? 'text') === 'number') {
                $fieldRules[] = 'integer';
                $fieldRules[] = 'min:'.($definition['min'] ?? 0);
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
