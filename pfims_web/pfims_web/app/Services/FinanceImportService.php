<?php

namespace App\Services;

use App\Exceptions\ImportValidationException;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FinanceImportService
{
    private const PROJECT_COST_COMPONENTS = ['material', 'labor', 'equipment', 'other'];

    private const REQUIRED_HEADERS = ['category_code', 'expense_description', 'amount', 'expense_date'];

    private const ALLOWED_HEADERS = ['project_name', 'category_code', 'project_cost_component', 'expense_description', 'amount', 'expense_date', 'remarks'];

    public function __construct(private TabularImportReader $reader) {}

    public function import(UploadedFile $file): array
    {
        $sheet = $this->reader->read($file);
        $this->validateHeaders($sheet['headers']);

        $projects = DB::table('project_tbl')->select('project_id', 'project_name')->get()
            ->groupBy(fn ($row) => $this->key($row->project_name));
        $categories = DB::table('fin_expense_category_tbl')
            ->where('is_active', true)
            ->select('fin_category_id', 'category_code', 'category_name', 'classification')
            ->get();
        $categoriesByCode = $categories->groupBy(fn ($row) => $this->key($row->category_code));
        $categoriesByName = $categories->groupBy(fn ($row) => $this->key($row->category_name));

        $prepared = [];
        $errors = [];
        $fileKeys = [];

        foreach ($sheet['rows'] as $row) {
            $values = $row['values'];
            $values['project_name'] = $values['project_name'] ?? null;
            $values['project_cost_component'] = $this->normalizeCostComponent($values['project_cost_component'] ?? null);
            $values['remarks'] = $values['remarks'] ?? null;
            $values['expense_date'] = $this->normalizeDate($values['expense_date'] ?? null);

            $validator = Validator::make($values, [
                'project_name' => ['nullable', 'string', 'max:100'],
                'category_code' => ['required', 'string', 'max:100'],
                'project_cost_component' => ['nullable', 'string', 'in:'.implode(',', self::PROJECT_COST_COMPONENTS)],
                'expense_description' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
                'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'remarks' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $this->appendValidationErrors($errors, $row['row'], $validator->errors()->toArray());

                continue;
            }

            $data = $validator->validated();
            $projectId = null;
            if (! blank($data['project_name'] ?? null)) {
                $matches = $projects->get($this->key($data['project_name']), collect());
                if ($matches->count() !== 1) {
                    $errors[] = $this->rowError($row['row'], 'project_name', $matches->isEmpty()
                        ? 'Project not found. Use a project name already configured in PFIMS.'
                        : 'Project name is ambiguous because multiple projects use this name.');

                    continue;
                }
                $projectId = (int) $matches->first()->project_id;
            }

            $categoryMatches = $categoriesByCode->get($this->key($data['category_code']), collect());
            if ($categoryMatches->isEmpty()) {
                $categoryMatches = $categoriesByName->get($this->key($data['category_code']), collect());
            }
            if ($categoryMatches->count() !== 1) {
                $errors[] = $this->rowError($row['row'], 'category_code', $categoryMatches->isEmpty()
                    ? 'Active finance category not found. Use a category code from Settings > Configurations.'
                    : 'Finance category is ambiguous.');

                continue;
            }
            $category = $categoryMatches->first();
            $classification = strtolower((string) ($category->classification ?? ''));
            $isDirect = $classification === 'direct';
            if ($isDirect && $projectId === null) {
                $errors[] = $this->rowError($row['row'], 'project_name', 'A direct project expense requires a valid project.');

                continue;
            }
            if (($isDirect || $projectId !== null) && blank($data['project_cost_component'] ?? null)) {
                $errors[] = $this->rowError($row['row'], 'project_cost_component', 'Select a project cost component for project expenses.');

                continue;
            }

            $record = [
                'project_id' => $projectId,
                'fin_category_id' => (int) $category->fin_category_id,
                'project_cost_component' => $this->normalizeCostComponent($data['project_cost_component'] ?? null),
                'expense_description' => trim($data['expense_description']),
                'amount' => round((float) $data['amount'], 2),
                'expense_date' => $data['expense_date'],
                'remarks' => blank($data['remarks'] ?? null) ? null : trim($data['remarks']),
            ];
            $naturalKey = $this->naturalKey($record);
            if (isset($fileKeys[$naturalKey])) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'Duplicates row '.$fileKeys[$naturalKey].' in this file.');

                continue;
            }
            $fileKeys[$naturalKey] = $row['row'];

            if ($this->duplicateQuery($record)->exists()) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'This expense already exists in PFIMS.');

                continue;
            }

            $prepared[] = ['row' => $row['row'], 'data' => $record];
        }

        if ($errors !== []) {
            throw new ImportValidationException('No rows were imported. Correct the listed row errors and upload the file again.', $errors);
        }

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $row) {
                if ($this->duplicateQuery($row['data'])->lockForUpdate()->exists()) {
                    throw new ImportValidationException('No rows were imported because a duplicate was created while the file was being checked.', [
                        $this->rowError($row['row'], 'duplicate', 'This expense now already exists in PFIMS.'),
                    ]);
                }
                DB::table('fin_expense_tbl')->insert($row['data'] + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return ['imported' => count($prepared), 'type' => 'finance_expenses'];
    }

    private function validateHeaders(array $headers): void
    {
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        $unexpected = array_values(array_diff($headers, self::ALLOWED_HEADERS));
        if ($missing !== [] || $unexpected !== []) {
            $parts = [];
            if ($missing !== []) {
                $parts[] = 'Missing: '.implode(', ', $missing).'.';
            }
            if ($unexpected !== []) {
                $parts[] = 'Unexpected: '.implode(', ', $unexpected).'.';
            }
            throw new ImportValidationException('Invalid finance-import headers. '.implode(' ', $parts));
        }
    }

    private function normalizeDate(mixed $value): mixed
    {
        if (is_numeric($value) && (float) $value >= 1 && (float) $value < 100000) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }
        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, trim($value));
                if ($date !== false && $date->format($format) === trim($value)) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return $value;
    }

    private function duplicateQuery(array $record)
    {
        $query = DB::table('fin_expense_tbl')
            ->where('fin_category_id', $record['fin_category_id'])
            ->whereDate('expense_date', $record['expense_date'])
            ->where('amount', $record['amount'])
            ->whereRaw('LOWER(TRIM(expense_description)) = ?', [$this->key($record['expense_description'])]);

        $query = $record['project_id'] === null ? $query->whereNull('project_id') : $query->where('project_id', $record['project_id']);

        return $record['project_cost_component'] === null ? $query->whereNull('project_cost_component') : $query->where('project_cost_component', $record['project_cost_component']);
    }

    private function naturalKey(array $record): string
    {
        return implode('|', [$record['project_id'] ?? 'office', $record['fin_category_id'], $record['project_cost_component'] ?? 'none', $record['expense_date'], number_format($record['amount'], 2, '.', ''), $this->key($record['expense_description'])]);
    }

    private function normalizeCostComponent(mixed $value): ?string
    {
        $component = $this->key($value);

        return $component === '' ? null : $component;
    }

    private function key(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function appendValidationErrors(array &$errors, int $row, array $messages): void
    {
        foreach ($messages as $field => $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $errors[] = $this->rowError($row, $field, $message);
            }
        }
    }

    private function rowError(int $row, string $field, string $message): array
    {
        return compact('row', 'field', 'message');
    }
}
