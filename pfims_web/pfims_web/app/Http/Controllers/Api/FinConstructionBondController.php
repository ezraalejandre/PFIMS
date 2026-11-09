<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinConstructionBond;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Group the fin construction bond actions.
class FinConstructionBondController extends Controller
{
    // List the fin construction bond records.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'status' => ['nullable', 'in:active,released,forfeited'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $query = FinConstructionBond::with('project:project_id,project_name');

        // Filter by project if provided
        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        // Filter by status if provided
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Run this step if the filters month has a value.
        if (!empty($filters['month'])) {
            $start = \Carbon\Carbon::createFromFormat('!Y-m', $filters['month']);
            $query->where('bond_date', '>=', $start->toDateString())
                ->where('bond_date', '<', $start->copy()->addMonth()->toDateString());
        }
        return response()->json($query->get());
    }

    // Save a new fin construction bond record.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:project_tbl,project_id',
            'bond_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|gt:0|max:999999999999.99',
            'bond_provider' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,released,forfeited',
            'remarks' => 'nullable|string|max:255',
        ]);

        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->normalize($validator->validated());
        $data['status'] = $data['status'] ?? 'active';
        // Return early if a matching record already exists.
        if ($this->isDuplicate($data)) {
            return response()->json(['message' => 'This construction bond already exists.'], 409);
        }
        $bond = FinConstructionBond::create($data);

        return response()->json($bond, 201);
    }

    // Save changes to the fin construction bond record.
    public function update(Request $request, $id)
    {
        $bond = FinConstructionBond::find($id);
        // Return early if the bond is missing or false.
        if (! $bond) {
            return response()->json(['message' => 'Bond not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => 'sometimes|required|exists:project_tbl,project_id',
            'bond_date' => 'sometimes|required|date|before_or_equal:today',
            'amount' => 'sometimes|required|numeric|gt:0|max:999999999999.99',
            'bond_provider' => 'nullable|string|max:100',
            'status' => 'sometimes|required|in:active,released,forfeited',
            'remarks' => 'nullable|string|max:255',
        ]);
        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $this->normalize($validator->validated());
        $candidate = array_merge($bond->only($bond->getFillable()), $data);
        // Return early if a matching record already exists.
        if ($this->isDuplicate($candidate, (int) $id)) {
            return response()->json(['message' => 'This construction bond already exists.'], 409);
        }
        $bond->update($data);

        return response()->json($bond);
    }

    // Delete the selected fin construction bond record.
    public function destroy($id)
    {
        $bond = FinConstructionBond::find($id);
        // Return early if the bond is missing or false.
        if (! $bond) {
            return response()->json(['message' => 'Bond not found'], 404);
        }

        $bond->delete();

        return response()->json(['message' => 'Bond deleted successfully']);
    }

    // Clean up the supplied values.
    private function normalize(array $data): array
    {
        // Process each field.
        foreach (['bond_provider', 'remarks'] as $field) {
            // Run this step if the data check matches.
            if (array_key_exists($field, $data)) {
                $data[$field] = blank($data[$field]) ? null : trim($data[$field]);
            }
        }

        return $data;
    }

    // Check the duplicate condition.
    private function isDuplicate(array $data, ?int $ignoreId = null): bool
    {
        $query = DB::table('fin_construction_bond_tbl')
            ->where('project_id', $data['project_id'])
            ->whereDate('bond_date', $data['bond_date'])
            ->where('amount', $data['amount']);
        $query = blank($data['bond_provider'] ?? null)
            ? $query->whereNull('bond_provider')
            : $query->whereRaw('LOWER(TRIM(bond_provider)) = ?', [strtolower(trim($data['bond_provider']))]);
        // Run this step if the ignore id is not null.
        if ($ignoreId !== null) {
            $query->where('bond_id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
