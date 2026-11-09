<?php

namespace App\Http\Controllers;

use App\Models\FinExpenseCategory;
use App\Models\FinanceComponent;
use App\Models\InventoryCategory;
use App\Models\ProjectPhase;
use App\Models\ProjectManager;
use App\Models\Unit;
use App\Services\ProjectPhaseProgressService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Group the config actions.
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
        'project_managers' => [
            'model' => ProjectManager::class,
            'table' => 'project_manager_tbl',
            'id' => 'manager_id',
            'name' => 'manager_name',
            'fields' => [
                'manager_name' => ['label' => 'Project manager', 'type' => 'text', 'required' => true, 'max' => 150],
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

    // Set up the values this class needs.
    public function __construct(private ProjectPhaseProgressService $phaseProgress) {}

    // List the config records.
    public function index(Request $request, string $type): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        $query = $model::query();
        // Run this step if the type equals exp categories.
        if ($type === 'exp_categories') {
            $query->whereNotIn('category_code', ['ADMINISTRATIVE_EXPENSES', 'SSS_PHILHEALTH_CONSTBOND']);
        }
        // Run this step if the type equals project phases.
        if ($type === 'project_phases') {
            $query->orderBy('stage_order')->orderBy('phase_id');
        } else {
            $query->orderBy($config['name']);
        }
        // Run this step if the filled check matches.
        if ($request->filled('search')) {
            $query->where($config['name'], 'like', '%'.trim((string) $request->input('search')).'%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
            'meta' => $this->meta($config),
        ]);
    }

    // Save a new config record.
    public function store(Request $request, string $type): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $validated = $this->normalize($type, $request->validate($this->rules($config)));
        $this->rejectDuplicate($config, $validated);
        // Run this step if the type equals exp categories.
        if ($type === 'exp_categories') {
            $validated['category_code'] = $this->generatedCategoryCode($validated);
            $validated['is_active'] = true;
        }
        $model = $config['model'];
        // Run these database steps together. Undo changes if a step fails.
        $item = DB::transaction(function () use ($type, $validated, $model) {
            // Return early if the type does not equal project phases.
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

    // Save changes to the config record.
    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        /** @var Model $item */
        $item = $model::findOrFail($id);
        $validated = $this->normalize($type, $request->validate($this->rules($config)));
        $this->rejectDuplicate($config, $validated, $id);
        // Run this step if the type equals exp categories.
        if ($type === 'exp_categories') $validated['category_code'] = $this->generatedCategoryCode($validated, $id);
        // Run these database steps together. Undo changes if a step fails.
        DB::transaction(function () use ($type, $item, $validated) {
            // Run this step if the type does not equal project phases.
            if ($type !== 'project_phases') {
                // Run this step if the type equals project managers.
                if ($type === 'project_managers') {
                    DB::table('project_tbl')->whereRaw('LOWER(TRIM(project_manager)) = ?', [Str::lower(trim((string) $item->manager_name))])
                        ->update(['project_manager' => $validated['manager_name']]);
                }
                $item->update($validated);
                return;
            }

            $oldName = (string) $item->phase_name;
            $oldStage = (int) $item->stage_order;
            $newStage = min((int) $validated['stage_order'], ProjectPhase::query()->count());

            // Run this step if the new stage is below old stage.
            if ($newStage < $oldStage) {
                ProjectPhase::query()->where($item->getKeyName(), '!=', $item->getKey())
                    ->whereBetween('stage_order', [$newStage, $oldStage - 1])->increment('stage_order');
            } elseif ($newStage > $oldStage) {
                ProjectPhase::query()->where($item->getKeyName(), '!=', $item->getKey())
                    ->whereBetween('stage_order', [$oldStage + 1, $newStage])->decrement('stage_order');
            }

            $validated['stage_order'] = $newStage;
            $item->update($validated);
            // Run this step if the old name does not equal phase name.
            if (trim($oldName) !== trim((string) $item->phase_name)) {
                DB::table('project_tbl')->whereRaw('LOWER(TRIM(phase)) = ?', [Str::lower(trim($oldName))])
                    ->update(['phase' => $item->phase_name]);
            }
            $this->phaseProgress->normalizeStageOrder();
            $this->phaseProgress->syncAllProjects();
        });

        return response()->json(['success' => true, 'data' => $item->fresh(), 'message' => 'Configuration updated successfully.']);
    }

    // Delete the selected config record.
    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $config = $this->configuration($type);
        $model = $config['model'];
        /** @var Model $item */
        $item = $model::findOrFail($id);
        $dependency = match ($type) {
            'project_managers' => DB::table('project_tbl')->whereRaw('LOWER(TRIM(project_manager)) = ?', [Str::lower(trim((string) $item->manager_name))])->exists() ? 'projects' : null,
            'units' => DB::table('inventory_item_tbl')->where('unit_id', $id)->exists() ? 'inventory items' : null,
            'inv_categories' => DB::table('inventory_item_tbl')->where('inventory_category_id', $id)->exists() ? 'inventory items' : null,
            'exp_categories' => DB::table('fin_expense_tbl')->where('fin_category_id', $id)->exists() ? 'finance expenses' : null,
            'project_phases' => DB::table('project_tbl')->whereRaw('LOWER(TRIM(phase)) = ?', [Str::lower(trim((string) $item->phase_name))])->exists() ? 'projects' : null,
            'finance_components' => DB::table('fin_expense_tbl')->whereRaw('LOWER(TRIM(project_cost_component)) = ?', [Str::lower(trim((string) $item->component_name))])->exists() ? 'finance expenses' : null,
            default => null,
        };
        // Return early if the dependency is set.
        if ($dependency) {
            return response()->json(['success' => false, 'message' => "This configuration cannot be deleted because it is used by {$dependency}."], 409);
        }
        // Run these database steps together. Undo changes if a step fails.
        DB::transaction(function () use ($type, $item) {
            $item->delete();
            // Run this step if the type equals project phases.
            if ($type === 'project_phases') {
                $this->phaseProgress->normalizeStageOrder();
                $this->phaseProgress->syncAllProjects();
            }
        });

        return response()->json(['success' => true, 'message' => 'Configuration deleted successfully.']);
    }

    // Get the configuration.
    private function configuration(string $type): array
    {
        abort_unless(isset($this->map[$type]), 404, 'Configuration type not found.');
        // Run this step if the type equals project phases.
        if ($type === 'project_phases') {
            $this->phaseProgress->ensureSchema();
        }

        return $this->map[$type];
    }

    // List the rules used to check these fields.
    private function rules(array $config): array
    {
        $rules = [];
        // Process each definition.
        foreach ($config['fields'] as $field => $definition) {
            $fieldRules = [$definition['required'] ? 'required' : 'nullable'];
            // Run this step if the definition type equals select.
            if (($definition['type'] ?? 'text') === 'select') {
                $fieldRules[] = 'in:'.implode(',', array_keys($definition['options']));
            } elseif (($definition['type'] ?? 'text') === 'number') {
                $fieldRules[] = 'integer';
                $fieldRules[] = 'min:'.($definition['min'] ?? 0);
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:'.$definition['max'];
            }
            // Run this step if the field equals contact number.
            if ($field === 'contact_number') {
                $fieldRules[] = 'regex:/^(?=.*\d)[0-9+().\s-]+$/';
            }
            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    // Clean up the supplied values.
    private function normalize(string $type, array $data): array
    {
        // Process each value.
        foreach ($data as $field => $value) {
            // Run this step if the value check matches.
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }
        // Run this step if the type equals exp categories.
        if ($type === 'exp_categories') {
            // Stop if the data category name equals administrative expenses.
            if (Str::lower(trim((string) $data['category_name'])) === 'administrative expenses') {
                throw ValidationException::withMessages(['category_name' => 'Choose a specific category; Admin is an expense type.']);
            }
        }

        return $data;
    }

    // Get the reject duplicate.
    private function rejectDuplicate(array $config, array $data, ?int $ignoreId = null): void
    {
        $fields = [$config['name']];
        // Process each field.
        foreach ($fields as $field) {
            $query = DB::table($config['table'])->whereRaw("LOWER(TRIM({$field})) = ?", [Str::lower(trim((string) $data[$field]))]);
            // Run this step if the config table equals fin expense category tbl.
            if ($config['table'] === 'fin_expense_category_tbl') $query->where('classification', $data['classification']);
            // Run this step if the ignore id is not null.
            if ($ignoreId !== null) {
                $query->where($config['id'], '!=', $ignoreId);
            }
            abort_if($query->exists(), 409, ucfirst(str_replace('_', ' ', $field)).' already exists.');
        }
    }

    // Get the generated category code.
    private function generatedCategoryCode(array $data, ?int $ignoreId = null): string
    {
        $base = trim((string) preg_replace('/[^A-Z0-9]+/', '_', Str::upper($data['category_name'])), '_');
        // Stop if the base checks match.
        if ($base === '' || ! preg_match('/^[A-Z]/', $base)) {
            throw ValidationException::withMessages(['category_name' => 'Category name must begin with a letter.']);
        }
        $query = DB::table('fin_expense_category_tbl')->where('category_code', $base);
        // Run this step if the ignore id is not null.
        if ($ignoreId !== null) $query->where('fin_category_id', '!=', $ignoreId);
        $code = $query->exists() ? Str::upper($data['classification']).'_'.$base : $base;
        // Stop if the code and ignore id checks match.
        if (strlen($code) > 40 || DB::table('fin_expense_category_tbl')->where('category_code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->where('fin_category_id', '!=', $ignoreId))->exists()) {
            throw ValidationException::withMessages(['category_name' => 'This name cannot produce a unique category code within 40 characters.']);
        }
        return $code;
    }

    // Get the meta.
    private function meta(array $config): array
    {
        return ['id' => $config['id'], 'name' => $config['name'], 'fields' => $config['fields']];
    }

    // Get the authorize admin.
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->user()?->role) === 'admin', 403, 'Only administrators can manage configurations.');
    }
}
