<?php

namespace App\Services;

use App\Exceptions\ImportValidationException;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProjectImportService
{
    private const HEADERS = [
        'project_name', 'client_name', 'project_manager', 'start_date', 'estimated_end_date',
        'actual_end_date', 'worker_count', 'phase', 'status', 'budget',
    ];

    private const STATUSES = ['Pending', 'On Track', 'At Risk', 'Delayed', 'Completed'];

    public function __construct(
        private TabularImportReader $reader,
        private ProjectPhaseProgressService $phaseProgress,
    ) {}

    public function import(UploadedFile $file): array
    {
        $sheet = $this->reader->read($file);
        $missing = array_values(array_diff(self::HEADERS, $sheet['headers']));
        $unexpected = array_values(array_diff($sheet['headers'], self::HEADERS));
        if ($missing !== [] || $unexpected !== []) {
            $parts = [];
            if ($missing !== []) $parts[] = 'Missing: '.implode(', ', $missing).'.';
            if ($unexpected !== []) $parts[] = 'Unexpected: '.implode(', ', $unexpected).'.';
            throw new ImportValidationException('Invalid project-import headers. '.implode(' ', $parts));
        }

        $phases = $this->phaseProgress->phases()->groupBy(fn ($phase) => $this->key($phase->phase_name));
        $prepared = [];
        $errors = [];
        $fileKeys = [];

        foreach ($sheet['rows'] as $row) {
            $values = $row['values'];
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
            if ($validator->fails()) {
                $this->appendValidationErrors($errors, $row['row'], $validator->errors()->toArray());
                continue;
            }

            $data = $validator->validated();
            $phaseMatches = $phases->get($this->key($data['phase']), collect());
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
            if (isset($fileKeys[$key])) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'Duplicates row '.$fileKeys[$key].' in this file.');
                continue;
            }
            $fileKeys[$key] = $row['row'];
            if ($this->duplicateExists($record)) {
                $errors[] = $this->rowError($row['row'], 'duplicate', 'A project with the same name, client, and start date already exists in PFIMS.');
                continue;
            }
            $prepared[] = ['row' => $row['row'], 'data' => $record];
        }

        if ($errors !== []) {
            throw new ImportValidationException('No rows were imported. Correct the listed row errors and upload the file again.', $errors);
        }

        $projectIds = DB::transaction(function () use ($prepared) {
            $ids = [];
            foreach ($prepared as $row) {
                $record = $row['data'];
                if ($this->duplicateExists($record, true)) {
                    throw new ImportValidationException('No rows were imported because a duplicate was created while the file was being checked.', [
                        $this->rowError($row['row'], 'duplicate', 'A project with the same name, client, and start date now exists in PFIMS.'),
                    ]);
                }
                $budget = $record['budget'];
                unset($record['budget']);
                $projectId = DB::table('project_tbl')->insertGetId($record);
                DB::table('budgets_tbl')->insert([
                    'project_id' => $projectId,
                    'budget_amount' => $budget,
                    'actual_amount' => 0,
                ]);
                $ids[] = (int) $projectId;
            }
            return $ids;
        });

        return ['imported' => count($prepared), 'type' => 'projects', 'project_ids' => $projectIds];
    }

    private function duplicateExists(array $record, bool $lock = false): bool
    {
        $query = DB::table('project_tbl')->whereDate('start_date', $record['start_date']);
        if ($lock) $query->lockForUpdate();
        return $query->get(['project_name', 'client_name'])->contains(fn ($project) =>
            $this->key($project->project_name) === $this->key($record['project_name'])
            && $this->key($project->client_name) === $this->key($record['client_name'])
        );
    }

    private function naturalKey(array $record): string
    {
        return implode('|', [$this->key($record['project_name']), $this->key($record['client_name']), $record['start_date']]);
    }

    private function normalizeDate(mixed $value): mixed
    {
        if (is_numeric($value) && (float) $value >= 1 && (float) $value < 100000) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }
        if (! is_string($value) || trim($value) === '') return $value;
        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, trim($value));
                if ($date !== false && $date->format($format) === trim($value)) return $date->format('Y-m-d');
            } catch (\Throwable) {
            }
        }
        return $value;
    }

    private function label(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function key(mixed $value): string
    {
        return Str::lower($this->label((string) $value));
    }

    private function appendValidationErrors(array &$errors, int $row, array $messages): void
    {
        foreach ($messages as $field => $fieldMessages) {
            foreach ($fieldMessages as $message) $errors[] = $this->rowError($row, $field, $message);
        }
    }

    private function rowError(int $row, string $field, string $message): array
    {
        return compact('row', 'field', 'message');
    }
}
