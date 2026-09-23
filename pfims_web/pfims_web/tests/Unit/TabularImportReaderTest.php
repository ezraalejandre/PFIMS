<?php

namespace Tests\Unit;

use App\Exceptions\ImportValidationException;
use App\Services\TabularImportReader;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class TabularImportReaderTest extends TestCase
{
    public function test_csv_with_more_than_the_old_two_thousand_row_limit_is_accepted(): void
    {
        $rows = ['category_code,expense_description,amount,expense_date'];
        for ($row = 1; $row <= 3664; $row++) {
            $rows[] = "CONST_SUPPLY,Expense {$row},1.00,2026-01-01";
        }

        $path = tempnam(sys_get_temp_dir(), 'pfims-import-');
        file_put_contents($path, implode("\n", $rows));

        try {
            $file = new UploadedFile($path, 'finance-expenses.csv', 'text/csv', null, true);
            $sheet = (new TabularImportReader)->read($file);

            $this->assertCount(3664, $sheet['rows']);
        } finally {
            @unlink($path);
        }
    }

    public function test_only_populated_rows_count_toward_the_ten_thousand_row_limit(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pfims-import-');
        $contents = "category_code,expense_description,amount,expense_date\n"
            .str_repeat("\n", 10050)
            ."CONST_SUPPLY,One expense,1.00,2026-01-01\n";
        file_put_contents($path, $contents);

        try {
            $file = new UploadedFile($path, 'finance-expenses.csv', 'text/csv', null, true);
            $sheet = (new TabularImportReader)->read($file);

            $this->assertCount(1, $sheet['rows']);
        } finally {
            @unlink($path);
        }
    }

    public function test_more_than_ten_thousand_populated_rows_are_rejected_with_the_documented_limit(): void
    {
        $rows = ['category_code,expense_description,amount,expense_date'];
        for ($row = 1; $row <= TabularImportReader::MAX_ROWS + 1; $row++) {
            $rows[] = "CONST_SUPPLY,Expense {$row},1.00,2026-01-01";
        }

        $path = tempnam(sys_get_temp_dir(), 'pfims-import-');
        file_put_contents($path, implode("\n", $rows));

        try {
            $file = new UploadedFile($path, 'finance-expenses.csv', 'text/csv', null, true);

            $this->expectException(ImportValidationException::class);
            $this->expectExceptionMessage('A maximum of 10000 data rows may be imported at once.');
            (new TabularImportReader)->read($file);
        } finally {
            @unlink($path);
        }
    }
}
