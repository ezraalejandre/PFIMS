<?php

namespace App\Services;

use App\Exceptions\ImportValidationException;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Group the project import actions.
class ProjectImportService
{
    private const HEADERS = [
        'project_name', 'client_name', 'project_manager', 'start_date', 'estimated_end_date',
        'actual_end_date', 'worker_count', 'phase', 'status', 'budget',
    ];

    private const STATUSES = ['Ongoing', 'Pending', 'At Risk', 'Delayed', 'Completed'];

    // Set up the values this class needs.
    public function __construct(
        private TabularImportReader $reader,
        private ProjectPhaseProgressService $phaseProgress,
    ) {}

    // Get the import.
    public function import(UploadedFile $file): array
    {
        $sheet = $this->reader->read($file);
        $missing = array_values(array_diff(self::HEADERS, $sheet['headers']));
        $unexpected = array_values(array_diff($sheet['headers'], self::HEADERS));
        // Run this step if the missing and unexpected checks match.
        if ($missing !== [] || $unexpected !== []) {
            $parts = [];
            // Run this step if the missing is not empty.
            if ($missing !== []) $parts[] = 'Missing: '.implode(', ', $missing).'.';
            // Run this step if the unexpected is not empty.
            if ($unexpected !== []) $parts[] = 'Unexpected: '.implode(', ', $unexpected).'.';
            throw new ImportValidationException('Invalid project-import headers. '.implode(' ', $parts));
        }

        $phases = $this->phaseProgress->phases()->groupBy(fn ($phase) => $this->key($phase->phase_name));
        $prepared = [];
        $errors = [];
        $fileKeys = [];

        // Process each row.
        foreach ($sheet['rows'] as $row) {
            $values = $row['values'];
            // Process each field.
            foreach (['start_date', 'estimated_end_date', 'actual_end_date'] as $field) {
                $values[$field] = $this->normalizeDate($values[$field] ?? null);
            }
            $validator = Validator::make($values, [
                'project_name' => ['required', 'string', 'max:150'],
                'client_name' => ['required', 'string', 'max:150'],
                'project_manager' => ['required', 'string', 'max:150'],
                'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
                'estimated_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'],
                'actual_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
                'worker_count' => ['required', 'integer', 'min:0', 'max:100000'],
                'phase' => ['required', 'string', 'max:100'],
                'status' => ['required', 'string', 'in:'.implode(',', self::STATUSES)],
                'budget' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            ]);
            // Run this step if the submitted fields fail validation.
            if ($validator->fails()) {
                $this->appendValidationErrors($errors, $row['row'], $validator->errors()->toArray());
                continue;
            }

            $data = $validator->validated();
            $phaseMatches = $phases->get($this->key($data['phase']), collect());
            // Run this step if the count does not equal 1.
            if ($phaseMatches->count() !== 1) {
                $errors[] = $this->rowError($row['row'], 'phase', $phaseMatches->isEmpty()
                    ? 'Project phase not found in Settings > Configurations.'
                    : 'Project phase is ambiguous.');
                continue;
            }

            $record = [
                'project_name' => $this->label($data['project_name']),
                'client_name' => $this->label($data['client_name']),
                'project_manager' => $this->label($data['project_manager']),
                'start_date' => $data['start_date'],
                'estimated_end_date' => $data['estimated_end_date'],
                'actual_end_date' => blank($data['actual_end_date'] ?? null) ? null : $data['actual_end_date'],
                'worker_count' => (int) $data['worker_count'],
                'phase' => (string) $phaseMatches->first()->phase_name,
                'completion_percentage' => $this->phaseProgress->completionForPhase((string) $phaseMatches->first()->phase_name),
                'status' => $data['status'],
                'budget' => round((float) $data['budget'], 2),
            ];
            $key = $this->naturalKey($record);
            // Run this step if the key is set.
            if (isset($fileKeys[$key])) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'Duplicates row '.$fileKeys[$key].' in this file.');
                continue;
            }
            $fileKeys[$key] = $row['row'];
            // Run this step if the record check matches.
            if ($this->duplicateExists($record)) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'A project with the same name, client, and start date already exists in PFIMS.');
                continue;
            }
            $prepared[] = ['row' => $row['row'], 'data' => $record];
        }

        // Stop if the errors is not empty.
        if ($errors !== []) {
            throw new ImportValidationException('No rows were imported. Correct the listed row errors and upload the file again.', $errors);
        }

        // Run these database steps together. Undo changes if a step fails.
        $projectIds = DB::transaction(function () use ($prepared) {
            $ids = [];
            // Process each row.
            foreach ($prepared as $row) {
                $record = $row['data'];
                // Stop if the record check matches.
                if ($this->duplicateExists($record, true)) {
                    throw new ImportValidationException('No rows were imported because a duplicate was created while the file was being checked.', [
                        $this->rowError($row['row'], 'duplicate', 'A project with the same name, client, and start date now exists in PFIMS.'),
                    ]);
                }
                $budget = $record['budget'];
                unset($record['budget']);
                $projectId = DB::table('project_tbl')->insertGetId($record);
                app(BudgetHistoryService::class)->create([
                    'project_id' => $projectId,
                    'budget_amount' => $budget,
                    'actual_amount' => 0,
                ], 'Budget observed in a project import; original approval history is unavailable.');
                $ids[] = (int) $projectId;
            }
            return $ids;
        });

        return ['imported' => count($prepared), 'type' => 'projects', 'project_ids' => $projectIds];
    }

    // Get the duplicate exists.
    private function duplicateExists(array $record, bool $lock = false): bool
    {
        $query = DB::table('project_tbl')->whereDate('start_date', $record['start_date']);
        // Run this step if the lock is set.
        if ($lock) $query->lockForUpdate();
        return $query->get(['project_name', 'client_name'])->contains(fn ($project) =>
            $this->key($project->project_name) === $this->key($record['project_name'])
            && $this->key($project->client_name) === $this->key($record['client_name'])
        );
    }

    // Get the natural key.
    private function naturalKey(array $record): string
    {
        return implode('|', [$this->key($record['project_name']), $this->key($record['client_name']), $record['start_date']]);
    }

    // Clean up date.
    private function normalizeDate(mixed $value): mixed
    {
        // Return early if the value checks match.
        if (is_numeric($value) && (float) $value >= 1 && (float) $value < 100000) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }
        // Return early if the value checks match.
        if (! is_string($value) || trim($value) === '') return $value;
        // Process each format.
        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y'] as $format) {
            // Try this action and handle any errors below.
            try {
                $date = Carbon::createFromFormat('!'.$format, trim($value));
                // Return early if the date and format checks match.
                if ($date !== false && $date->format($format) === trim($value)) return $date->format('Y-m-d');
            } catch (\Throwable) {
            }
        }
        return $value;
    }

    // Get the label.
    private function label(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    // Get the key.
    private function key(mixed $value): string
    {
        return Str::lower($this->label((string) $value));
    }

    // Get the append validation errors.
    private function appendValidationErrors(array &$errors, int $row, array $messages): void
    {
        // Process each field messages.
        foreach ($messages as $field => $fieldMessages) {
            // Process each message.
            foreach ($fieldMessages as $message) $errors[] = $this->rowError($row, $field, $message);
        }
    }

    // Get the row error.
    private function rowError(int $row, string $field, string $message): array
    {
        return compact('row', 'field', 'message');
    }
}
