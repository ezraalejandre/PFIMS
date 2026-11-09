<?php

namespace App\Services;

use App\Exceptions\ImportValidationException;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;
use ZipArchive;

// Group the tabular import reader actions.
class TabularImportReader
{
    public const MAX_ROWS = 10000;

    private const MAX_COLUMNS = 50;

    private const MAX_UNCOMPRESSED_BYTES = 20_000_000;

    /**
     * @return array{headers: array<int, string>, rows: array<int, array{row: int, values: array<string, mixed>}>}
     */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // Stop if the extension check matches.
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw new ImportValidationException('Only CSV and XLSX files are supported.');
        }

        $matrix = $extension === 'csv'
            ? $this->readCsv($file->getRealPath())
            : $this->readXlsx($file->getRealPath());

        // Stop if the number of matrix is below 2.
        if (count($matrix) < 2) {
            throw new ImportValidationException('The import file must contain a header row and at least one data row.');
        }

        $rawHeaders = array_shift($matrix);
        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $rawHeaders);

        // Repeat while the headers checks match.
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        // Stop if the headers checks match.
        if ($headers === [] || in_array('', $headers, true)) {
            throw new ImportValidationException('Every populated column must have a header.');
        }

        // Stop if the number of headers does not equal headers.
        if (count($headers) !== count(array_unique($headers))) {
            throw new ImportValidationException('Duplicate column headers are not allowed.');
        }

        // Stop if the number of headers is above self max columns.
        if (count($headers) > self::MAX_COLUMNS) {
            throw new ImportValidationException('The import file has too many columns.');
        }

        $rows = [];
        // Process each raw row.
        foreach ($matrix as $index => $rawRow) {
            $rawRow = array_slice(array_pad($rawRow, count($headers), null), 0, count($headers));
            // Skip this item if the raw row check matches.
            if ($this->isBlankRow($rawRow)) {
                continue;
            }

            // Process each value.
            foreach ($rawRow as $column => $value) {
                // Reject invalid input in the import file.
                if (is_string($value) && PlainTextInput::invalid($value)) {
                    throw new ImportValidationException('Invalid input in the import file.', [['row'=>$index+2,'errors'=>[($headers[$column] ?? 'value')=>['Invalid input. Markup and executable code are not allowed.']]]]);
                }
            }
            $rows[] = [
                'row' => $index + 2,
                'values' => array_combine($headers, array_map([$this, 'cleanValue'], $rawRow)),
            ];

            // Stop if the number of rows is above self max rows.
            if (count($rows) > self::MAX_ROWS) {
                throw new ImportValidationException('A maximum of '.self::MAX_ROWS.' data rows may be imported at once.');
            }
        }

        // Stop if the rows is empty.
        if ($rows === []) {
            throw new ImportValidationException('The import file contains no data rows.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    // Read csv.
    private function readCsv(string $path): array
    {
        $contents = file_get_contents($path);
        // Stop if the contents checks match.
        if ($contents === false || str_contains($contents, "\0")) {
            throw new ImportValidationException('The uploaded file is not a valid text CSV file.');
        }
        // Stop if the contents check matches.
        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new ImportValidationException('CSV files must use UTF-8 encoding.');
        }

        $handle = fopen($path, 'rb');
        // Stop if the handle is false.
        if ($handle === false) {
            throw new ImportValidationException('The CSV file could not be read.');
        }

        $sample = fgets($handle) ?: '';
        rewind($handle);

        $delimiters = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);
        // Run this step if the delimiter equals 0.
        if (($delimiters[$delimiter] ?? 0) === 0) {
            fclose($handle);
            throw new ImportValidationException('The CSV header must contain comma-separated columns.');
        }

        $rows = [];
        // Repeat while the value check matches.
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    // Read xlsx.
    private function readXlsx(string $path): array
    {
        // Stop if the value check matches.
        if (! class_exists(ZipArchive::class)) {
            throw new ImportValidationException('XLSX import is unavailable because the PHP ZIP extension is disabled.');
        }

        $signature = file_get_contents($path, false, null, 0, 4);
        // Stop if the signature does not equal pk x03 x04.
        if ($signature !== "PK\x03\x04") {
            throw new ImportValidationException('The uploaded file is not a valid XLSX workbook.');
        }
        $zip = new ZipArchive;
        // Stop if the path is not true.
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ImportValidationException('The XLSX file is invalid or corrupted.');
        }

        // Try this action and handle any errors below.
        try {
            $uncompressed = 0;
            // Repeat for each step in this range.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $uncompressed += (int) ($stat['size'] ?? 0);
                // Stop if the uncompressed is above self max uncompressed bytes.
                if ($uncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new ImportValidationException('The XLSX file expands beyond the safe processing limit.');
                }
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $worksheetPath = $this->firstWorksheetPath($zip);
            $worksheetXml = $zip->getFromName($worksheetPath);
            // Stop if the worksheet xml is false.
            if ($worksheetXml === false) {
                throw new ImportValidationException('The first XLSX worksheet could not be read.');
            }

            $xml = $this->parseXml($worksheetXml, 'The XLSX worksheet is malformed.');
            $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rowNodes = $xml->xpath('//x:sheetData/x:row') ?: [];
            $rows = [];

            // Process each row node.
            foreach ($rowNodes as $rowNode) {
                $row = [];
                // Process each cell.
                foreach ($rowNode->c as $cell) {
                    $reference = (string) $cell['r'];
                    $column = $this->columnIndex($reference);
                    // Stop if the column is at least self max columns.
                    if ($column >= self::MAX_COLUMNS) {
                        throw new ImportValidationException('The XLSX file has too many columns.');
                    }
                    $row[$column] = $this->xlsxCellValue($cell, $sharedStrings);
                }

                // Run this step if the row is not empty.
                if ($row !== []) {
                    $max = max(array_keys($row));
                    $rows[] = array_replace(array_fill(0, $max + 1, null), $row);
                } else {
                    $rows[] = [];
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    // Read shared strings.
    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        // Return early if the content is false.
        if ($content === false) {
            return [];
        }

        $xml = $this->parseXml($content, 'The XLSX shared-string table is malformed.');
        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        // Build a result for each item.
        return array_map(function (SimpleXMLElement $item) {
            $parts = $item->xpath('.//x:t') ?: [];

            return implode('', array_map(fn ($part) => (string) $part, $parts));
        }, $xml->xpath('//x:si') ?: []);
    }

    // Get the first worksheet path.
    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbookContent = $zip->getFromName('xl/workbook.xml');
        $relationsContent = $zip->getFromName('xl/_rels/workbook.xml.rels');

        // Return early if the workbook content and relations content checks match.
        if ($workbookContent === false || $relationsContent === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = $this->parseXml($workbookContent, 'The XLSX workbook is malformed.');
        $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = $workbook->xpath('//x:sheets/x:sheet');
        // Stop if the sheets is missing or false.
        if (! $sheets) {
            throw new ImportValidationException('The XLSX workbook contains no worksheets.');
        }
        $relationshipAttributes = $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) $relationshipAttributes['id'];

        $relations = $this->parseXml($relationsContent, 'The XLSX workbook relationships are malformed.');
        // Process each relation.
        foreach ($relations->Relationship as $relation) {
            // Run this step if the relation id equals relationship id.
            if ((string) $relation['Id'] === $relationshipId) {
                $target = str_replace('\\', '/', (string) $relation['Target']);
                $target = preg_replace('#^(\.\./)+#', '', $target);

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/');
            }
        }

        throw new ImportValidationException('The first XLSX worksheet relationship is missing.');
    }

    // Get the xlsx cell value.
    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): mixed
    {
        // Stop if the xpath check matches.
        if ($cell->xpath('./*[local-name()="f"]')) throw new ImportValidationException('Invalid input: formulas are not allowed in imported cells.');
        $type = (string) $cell['t'];
        // Run this step if the type equals inline str.
        if ($type === 'inlineStr') {
            $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];

            return implode('', array_map(fn ($part) => (string) $part, $parts));
        }

        $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
        $raw = isset($valueNodes[0]) ? (string) $valueNodes[0] : null;
        // Return early if the raw is null.
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            's' => $sharedStrings[(int) $raw] ?? null,
            'b' => $raw === '1' ? '1' : '0',
            default => $raw,
        };
    }

    // Read xml.
    private function parseXml(string $content, string $error): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        // Stop if the xml is false.
        if ($xml === false) {
            throw new ImportValidationException($error);
        }

        return $xml;
    }

    // Get the column index.
    private function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/i', $reference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;
        // Process each letter.
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    // Clean up header.
    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        $header = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', $header));

        return trim($header, '_');
    }

    // Check the blank row condition.
    private function isBlankRow(array $row): bool
    {
        return count(array_filter($row, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0;
    }

    // Get the clean value.
    private function cleanValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
