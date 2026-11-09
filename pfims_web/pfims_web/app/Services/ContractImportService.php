<?php

namespace App\Services;

use App\Exceptions\ImportValidationException;
use App\Models\FinProjectContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Group the contract import actions.
class ContractImportService
{
    // Set up the values this class needs.
    public function __construct(private TabularImportReader $reader, private AuditLogService $audit) {}

    // Get the import.
    public function import(UploadedFile $file): array
    {
        $sheet = $this->reader->read($file);
        $headers = ['project_name', 'additional_works_contract', 'original_payment_received', 'additional_works_payment', 'remarks'];
        // Stop if the headers and sheet headers checks match.
        if (array_diff($headers, $sheet['headers']) || array_diff($sheet['headers'], $headers)) {
            throw new ImportValidationException('Use the Contracts template with these columns: '.implode(', ', $headers));
        }
        $records = [];
        $errors = [];
        $seen = [];
        // Process each row.
        foreach ($sheet['rows'] as $row) {
            $data = $row['values'];
            $projects = DB::table('project_tbl')->whereRaw('LOWER(TRIM(project_name)) = ?', [mb_strtolower(trim((string) $data['project_name']))])->get();
            $validator = Validator::make($data, [
                'project_name' => 'required|string|max:150',
                'additional_works_contract' => 'required|numeric|min:0|max:99999999999999.99',
                'original_payment_received' => 'required|numeric|min:0|max:99999999999999.99',
                'additional_works_payment' => 'required|numeric|min:0|max:99999999999999.99',
                'remarks' => 'nullable|string|max:255',
            ]);
            $message = $validator->fails() ? $validator->errors()->first() : null;
            $project = $projects->count() === 1 ? $projects->first() : null;
            $budget = $project ? DB::table('budgets_tbl')->where('project_id', $project->project_id)->value('budget_amount') : null;
            // Run this step if the project is missing or false.
            if (! $project) $message = 'Project name must match exactly one existing project.';
            elseif ((float) $budget <= 0) $message = 'Add a budget for this project before recording a contract.';
            elseif (isset($seen[$project->project_id]) || DB::table('fin_project_contract_tbl')->where('project_id', $project->project_id)->exists()) $message = 'A contract already exists for this project.';
            // Run this step if the message is set.
            if ($message) {
                $errors[] = ['row' => $row['row'], 'field' => 'contract', 'message' => $message];
                continue;
            }
            $seen[$project->project_id] = true;
            $records[] = ['project_id' => $project->project_id, 'original_contract_price' => $budget,
                'additional_works_contract' => $data['additional_works_contract'], 'original_payment_received' => $data['original_payment_received'],
                'additional_works_payment' => $data['additional_works_payment'], 'remarks' => $data['remarks'] ?: null];
        }
        // Stop if the errors is set.
        if ($errors) throw new ImportValidationException('Contracts import validation failed.', $errors);
        // Run these database steps together. Undo changes if a step fails.
        DB::transaction(function () use ($records) {
            // Process each record.
            foreach ($records as $record) {
                DB::table('project_tbl')->where('project_id', $record['project_id'])->lockForUpdate()->first();
                // Stop if the exists project id check matches.
                if (DB::table('fin_project_contract_tbl')->where('project_id', $record['project_id'])->exists()) {
                    throw new ImportValidationException('A contract already exists for one of the selected projects.');
                }
                $budget = DB::table('budgets_tbl')->where('project_id', $record['project_id'])->value('budget_amount');
                // Stop if the budget is at most 0.
                if ((float) $budget <= 0) throw new ImportValidationException('Add a budget for this project before recording a contract.');
                $record['original_contract_price'] = $budget;
                $contract = FinProjectContract::create($record);
                $this->audit->record($contract, 'CREATE', [], $contract->getAttributes());
            }
        });
        return ['imported' => count($records), 'skipped' => 0];
    }
}
