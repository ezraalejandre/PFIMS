<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

/** Builds the same selected report columns for CSV, Excel and PDF. */
class ReportFileBuilder
{
    public function build(string $format, string $title, string $scope, array $columns, array $rows, ?array $totals): string
    {
        return match ($format) {
            'csv' => $this->csv($title, $scope, $columns, $rows, $totals),
            'xlsx' => $this->xlsx($title, $scope, $columns, $rows, $totals),
            'pdf' => $this->pdf($title, $scope, $columns, $rows, $totals),
        };
    }

    private function csv(string $title, string $scope, array $columns, array $rows, ?array $totals): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [$title]);
        fputcsv($stream, ['As of '.now()->format('F j, Y')]);
        fputcsv($stream, [$scope]);
        fputcsv($stream, []);
        fputcsv($stream, array_values($columns));
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($key) => $this->safeCsv($row[$key] ?? ''), array_keys($columns)));
        }
        if ($totals !== null) {
            fputcsv($stream, array_map(fn ($key) => $this->safeCsv($totals[$key] ?? ''), array_keys($columns)));
        }
        rewind($stream);
        $result = stream_get_contents($stream);
        fclose($stream);
        return $result;
    }

    private function safeCsv(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value)) {
            return "'".$value;
        }
        return $value;
    }

    private function pdf(string $title, string $scope, array $columns, array $rows, ?array $totals): string
    {
        $logo = base64_encode(file_get_contents(public_path('images/report-header.jpeg')));
        $head = implode('', array_map(fn ($label) => '<th>'.$this->html($label).'</th>', array_values($columns)));
        $body = '';
        foreach ($rows as $row) {
            $body .= $this->htmlRow($columns, $row);
        }
        if ($totals !== null) {
            $body .= $this->htmlRow($columns, $totals, true);
        }
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 28px; } body { font-family: DejaVu Sans, sans-serif; color:#17283a; font-size:8px; }
            header { text-align:center; margin-bottom:15px; } header img { width:330px; height:auto; }
            h1 { font-size:14px; margin:7px 0 3px; } p { margin:2px 0; }
            table { border-collapse:collapse; width:100%; table-layout:fixed; margin-top:12px; }
            th { background:#1a2b3c; color:white; font-size:7px; padding:6px 3px; overflow-wrap:anywhere; }
            td { border-bottom:1px solid #dce2e8; padding:5px 3px; text-align:right; overflow-wrap:anywhere; }
            td:first-child { text-align:left; } tr.total td { font-weight:bold; border-top:2px solid #1a2b3c; background:#f4f6f9; }
            thead { display:table-header-group; } tr { page-break-inside:avoid; }
            </style></head><body><header><img src="data:image/jpeg;base64,'.$logo.'"><h1>'.$this->html($title).'</h1>
            <p>As of '.now()->format('F j, Y').'</p><p>'.$this->html($scope).'</p></header>
            <table><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table></body></html>';
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a3', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();
        return $dompdf->output();
    }

    private function htmlRow(array $columns, array $row, bool $total = false): string
    {
        $cells = '';
        foreach (array_keys($columns) as $key) {
            $value = $row[$key] ?? '';
            $number = is_numeric($value) && ! in_array($key, ['project_name', 'item_name', 'category_name', 'supplier_name', 'unit_name', 'stock_status'], true);
            $display = $number ? number_format((float) $value, $key === 'item_id' ? 0 : 2) : $value;
            $cells .= '<td>'.$this->html($display).'</td>';
        }
        return '<tr'.($total ? ' class="total"' : '').'>'.$cells.'</tr>';
    }

    private function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function xlsx(string $title, string $scope, array $columns, array $rows, ?array $totals): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pfims-report-');
        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the Excel report.');
        }
        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Default Extension="jpeg" ContentType="image/jpeg"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
                .'</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd"/></numFmts>'
                .'<fonts count="3"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="13"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts>'
                .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1A2B3C"/><bgColor indexed="64"/></patternFill></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');

            $columnCount = count($columns);
            $lastColumn = $this->excelColumn($columnCount);
            $xmlRows = '<row r="1" ht="32" customHeight="1"/><row r="2" ht="32" customHeight="1"/><row r="3" ht="32" customHeight="1"/>';
            $xmlRows .= '<row r="5">'.$this->xlsxCell('A5', $title, 1).'</row>';
            $xmlRows .= '<row r="6">'.$this->xlsxCell('A6', 'As of '.now()->format('F j, Y')).'</row>';
            $xmlRows .= '<row r="7">'.$this->xlsxCell('A7', $scope, 1).'</row>';
            $headerCells = '';
            foreach (array_values($columns) as $index => $label) {
                $headerCells .= $this->xlsxCell($this->excelColumn($index + 1).'9', $label, 2);
            }
            $xmlRows .= '<row r="9" ht="42" customHeight="1">'.$headerCells.'</row>';
            $rowNumber = 10;
            foreach ($rows as $row) {
                $xmlRows .= $this->xlsxRow($rowNumber++, $columns, $row);
            }
            if ($totals !== null) {
                $xmlRows .= $this->xlsxRow($rowNumber, $columns, $totals, true);
            }
            $widths = '<cols>';
            foreach (array_keys($columns) as $index => $key) {
                $widths .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.($index === 0 ? 32 : 20).'" customWidth="1"/>';
            }
            $widths .= '</cols>';
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="17"/>'.$widths.'<sheetData>'.$xmlRows.'</sheetData>'
                .'<mergeCells count="3"><mergeCell ref="A5:'.$lastColumn.'5"/><mergeCell ref="A6:'.$lastColumn.'6"/><mergeCell ref="A7:'.$lastColumn.'7"/></mergeCells>'
                .'<drawing r:id="rId1"/></worksheet>');
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
                .'<xdr:ext cx="4191000" cy="1057275"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Company header"/><xdr:cNvPicPr/></xdr:nvPicPr>'
                .'<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                .'<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>');
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/report-header.jpeg"/>'
                .'</Relationships>');
            $zip->addFile(public_path('images/report-header.jpeg'), 'xl/media/report-header.jpeg');
        } finally {
            $zip->close();
        }
        $data = file_get_contents($file);
        unlink($file);
        return $data;
    }

    private function xlsxRow(int $number, array $columns, array $row, bool $total = false): string
    {
        $cells = '';
        foreach (array_keys($columns) as $index => $key) {
            $value = $row[$key] ?? '';
            if ($value !== '' && $value !== null && str_ends_with($key, '_date')) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10), new \DateTimeZone('UTC'));
                if ($date !== false) {
                    $value = ((int) $date->format('U') / 86400) + 25569;
                    $cells .= $this->xlsxCell($this->excelColumn($index + 1).$number, $value, 5);
                    continue;
                }
            }
            $isNumber = is_numeric($value) && ! in_array($key, ['project_name', 'item_name', 'category_name', 'supplier_name', 'unit_name', 'stock_status'], true);
            $cells .= $this->xlsxCell($this->excelColumn($index + 1).$number, $value,
                $isNumber ? ($key === 'item_id' ? 6 : ($total ? 4 : 3)) : ($total ? 1 : 0), ! $isNumber);
        }
        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private function xlsxCell(string $reference, mixed $value, int $style = 0, bool $forceText = false): string
    {
        if (! $forceText && is_numeric($value)) {
            return '<c r="'.$reference.'" s="'.$style.'"><v>'.(float) $value.'</v></c>';
        }
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->html($value).'</t></is></c>';
    }

    private function excelColumn(int $index): string
    {
        $result = '';
        while ($index > 0) {
            $index--;
            $result = chr(65 + $index % 26).$result;
            $index = intdiv($index, 26);
        }
        return $result;
    }
}
