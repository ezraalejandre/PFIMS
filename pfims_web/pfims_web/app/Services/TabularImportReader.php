<?php

namespace App\Services;

use App\Exceptions\ImportValidationException;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;
use ZipArchive;

class TabularImportReader
{
    private const MAX_ROWS = 2000;

    private const MAX_COLUMNS = 50;

    private const MAX_UNCOMPRESSED_BYTES = 20_000_000;

    /**
     * @return array{headers: array<int, string>, rows: array<int, array{row: int, values: array<string, mixed>}>}
     */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw new ImportValidationException('Only CSV and XLSX files are supported.');
        }

        $matrix = $extension === 'csv'
            ? $this->readCsv($file->getRealPath())
            : $this->readXlsx($file->getRealPath());

        if (count($matrix) < 2) {
            throw new ImportValidationException('The import file must contain a header row and at least one data row.');
        }

        $rawHeaders = array_shift($matrix);
        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), $rawHeaders);

        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        if ($headers === [] || in_array('', $headers, true)) {
            throw new ImportValidationException('Every populated column must have a header.');
        }

        if (count($headers) !== count(array_unique($headers))) {
            throw new ImportValidationException('Duplicate column headers are not allowed.');
        }

        if (count($headers) > self::MAX_COLUMNS) {
            throw new ImportValidationException('The import file has too many columns.');
        }

        $rows = [];
        foreach ($matrix as $index => $rawRow) {
            $rawRow = array_slice(array_pad($rawRow, count($headers), null), 0, count($headers));
            if ($this->isBlankRow($rawRow)) {
                continue;
            }

            $rows[] = [
                'row' => $index + 2,
                'values' => array_combine($headers, array_map([$this, 'cleanValue'], $rawRow)),
            ];

            if (count($rows) > self::MAX_ROWS) {
                throw new ImportValidationException('A maximum of '.self::MAX_ROWS.' data rows may be imported at once.');
            }
        }

        if ($rows === []) {
            throw new ImportValidationException('The import file contains no data rows.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function readCsv(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false || str_contains($contents, "\0")) {
            throw new ImportValidationException('The uploaded file is not a valid text CSV file.');
        }
        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new ImportValidationException('CSV files must use UTF-8 encoding.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new ImportValidationException('The CSV file could not be read.');
        }

        $sample = fgets($handle) ?: '';
        rewind($handle);

        $delimiters = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);
        if (($delimiters[$delimiter] ?? 0) === 0) {
            fclose($handle);
            throw new ImportValidationException('The CSV header must contain comma-separated columns.');
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS + 10) {
                fclose($handle);
                throw new ImportValidationException('The CSV file contains too many rows.');
            }
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new ImportValidationException('XLSX import is unavailable because the PHP ZIP extension is disabled.');
        }

        $signature = file_get_contents($path, false, null, 0, 4);
        if ($signature !== "PK\x03\x04") {
            throw new ImportValidationException('The uploaded file is not a valid XLSX workbook.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ImportValidationException('The XLSX file is invalid or corrupted.');
        }

        try {
            $uncompressed = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $uncompressed += (int) ($stat['size'] ?? 0);
                if ($uncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new ImportValidationException('The XLSX file expands beyond the safe processing limit.');
                }
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $worksheetPath = $this->firstWorksheetPath($zip);
            $worksheetXml = $zip->getFromName($worksheetPath);
            if ($worksheetXml === false) {
                throw new ImportValidationException('The first XLSX worksheet could not be read.');
            }

            $xml = $this->parseXml($worksheetXml, 'The XLSX worksheet is malformed.');
            $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rowNodes = $xml->xpath('//x:sheetData/x:row') ?: [];
            $rows = [];

            foreach ($rowNodes as $rowNode) {
                if (count($rows) > self::MAX_ROWS + 10) {
                    throw new ImportValidationException('The XLSX file contains too many rows.');
                }

                $row = [];
                foreach ($rowNode->c as $cell) {
                    $reference = (string) $cell['r'];
                    $column = $this->columnIndex($reference);
                    if ($column >= self::MAX_COLUMNS) {
                        throw new ImportValidationException('The XLSX file has too many columns.');
                    }
                    $row[$column] = $this->xlsxCellValue($cell, $sharedStrings);
                }

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

    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = $this->parseXml($content, 'The XLSX shared-string table is malformed.');
        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return array_map(function (SimpleXMLElement $item) {
            $parts = $item->xpath('.//x:t') ?: [];

            return implode('', array_map(fn ($part) => (string) $part, $parts));
        }, $xml->xpath('//x:si') ?: []);
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbookContent = $zip->getFromName('xl/workbook.xml');
        $relationsContent = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookContent === false || $relationsContent === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = $this->parseXml($workbookContent, 'The XLSX workbook is malformed.');
        $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = $workbook->xpath('//x:sheets/x:sheet');
        if (! $sheets) {
            throw new ImportValidationException('The XLSX workbook contains no worksheets.');
        }
        $relationshipAttributes = $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = (string) $relationshipAttributes['id'];

        $relations = $this->parseXml($relationsContent, 'The XLSX workbook relationships are malformed.');
        foreach ($relations->Relationship as $relation) {
            if ((string) $relation['Id'] === $relationshipId) {
                $target = str_replace('\\', '/', (string) $relation['Target']);
                $target = preg_replace('#^(\.\./)+#', '', $target);

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/');
            }
        }

        throw new ImportValidationException('The first XLSX worksheet relationship is missing.');
    }

    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): mixed
    {
        $type = (string) $cell['t'];
        if ($type === 'inlineStr') {
            $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];

            return implode('', array_map(fn ($part) => (string) $part, $parts));
        }

        $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
        $raw = isset($valueNodes[0]) ? (string) $valueNodes[0] : null;
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            's' => $sharedStrings[(int) $raw] ?? null,
            'b' => $raw === '1' ? '1' : '0',
            default => $raw,
        };
    }

    private function parseXml(string $content, string $error): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new ImportValidationException($error);
        }

        return $xml;
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/i', $reference, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        $header = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', $header));

        return trim($header, '_');
    }

    private function isBlankRow(array $row): bool
    {
        return count(array_filter($row, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0;
    }

    private function cleanValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
