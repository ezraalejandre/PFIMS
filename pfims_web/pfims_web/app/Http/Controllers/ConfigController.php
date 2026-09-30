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
use Illuminate\Validation\ValidationException;

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
                'category_name' => ['label' => 'Category name', 'type' => 'text', 'required' => true, 'max' => 100],
                'classification' => ['label' => 'Expense type', 'type' => 'select', 'required' => true, 'options' => ['direct' => 'Direct', 'admin' => 'Admin']],
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
        if ($type === 'exp_categories') {
            $query->whereNotIn('category_code', ['ADMINISTRATIVE_EXPENSES', 'SSS_PHILHEALTH_CONSTBOND']);
        }
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
        $validated = $this->normalize($type, $request->validate($this->rules($config)));
        $this->rejectDuplicate($config, $validated);
        if ($type === 'exp_categories') {
            $validated['category_code'] = $this->generatedCategoryCode($validated);
            $validated['is_active'] = true;
        }
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
        $validated = $this->normalize($type, $request->validate($this->rules($config)));
        $this->rejectDuplicate($config, $validated, $id);
        if ($type === 'exp_categories') $validated['category_code'] = $this->generatedCategoryCode($validated, $id);
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
            if (Str::lower(trim((string) $data['category_name'])) === 'administrative expenses') {
                throw ValidationException::withMessages(['category_name' => 'Choose a specific category; Admin is an expense type.']);
            }
        }

        return $data;
    }

    private function rejectDuplicate(array $config, array $data, ?int $ignoreId = null): void
    {
        $fields = [$config['name']];
        foreach ($fields as $field) {
            $query = DB::table($config['table'])->whereRaw("LOWER(TRIM({$field})) = ?", [Str::lower(trim((string) $data[$field]))]);
            if ($config['table'] === 'fin_expense_category_tbl') $query->where('classification', $data['classification']);
            if ($ignoreId !== null) {
                $query->where($config['id'], '!=', $ignoreId);
            }
            abort_if($query->exists(), 409, ucfirst(str_replace('_', ' ', $field)).' already exists.');
        }
    }

    private function generatedCategoryCode(array $data, ?int $ignoreId = null): string
    {
        $base = trim((string) preg_replace('/[^A-Z0-9]+/', '_', Str::upper($data['category_name'])), '_');
        if ($base === '' || ! preg_match('/^[A-Z]/', $base)) {
            throw ValidationException::withMessages(['category_name' => 'Category name must begin with a letter.']);
        }
        $query = DB::table('fin_expense_category_tbl')->where('category_code', $base);
        if ($ignoreId !== null) $query->where('fin_category_id', '!=', $ignoreId);
        $code = $query->exists() ? Str::upper($data['classification']).'_'.$base : $base;
        if (strlen($code) > 40 || DB::table('fin_expense_category_tbl')->where('category_code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->where('fin_category_id', '!=', $ignoreId))->exists()) {
            throw ValidationException::withMessages(['category_name' => 'This name cannot produce a unique category code within 40 characters.']);
        }
        return $code;
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
