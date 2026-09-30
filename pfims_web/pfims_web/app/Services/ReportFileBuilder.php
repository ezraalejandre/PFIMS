<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

/** Builds the same selected report columns for CSV, Excel and PDF. */
class ReportFileBuilder
{
    public function build(string $format, string $title, string $scope, array $columns, array $rows, ?array $totals, array $design = []): string
    {
        return match ($format) {
            'csv' => $this->csv($title, $scope, $columns, $rows, $totals),
            'xlsx' => $this->xlsx($title, $scope, $columns, $rows, $totals, $design),
            'pdf' => $this->pdf($title, $scope, $columns, $rows, $totals, $design),
        };
    }

    private function totalRows(?array $totals): array
    {
        if ($totals === null) return [];
        return array_is_list($totals) ? $totals : [$totals];
    }

    private function csv(string $title, string $scope, array $columns, array $rows, ?array $totals): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [$title]);
        fputcsv($stream, ['As of '.now()->format('F j, Y')]);
        fputcsv($stream, [$scope]);
        fputcsv($stream, []);
        fputcsv($stream, array_map('mb_strtoupper', array_values($columns)));
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($key) => $this->safeCsv($row[$key] ?? ''), array_keys($columns)));
        }
        foreach ($this->totalRows($totals) as $totalRow) {
            fputcsv($stream, array_map(fn ($key) => $this->safeCsv($totalRow[$key] ?? ''), array_keys($columns)));
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

    private function pdf(string $title, string $scope, array $columns, array $rows, ?array $totals, array $design): string
    {
        $headerColor = match ($design['header_color'] ?? 'navy') { 'orange' => '#c96c00', 'green' => '#176638', default => '#1a2b3c' };
        $cellPadding = ($design['table_spacing'] ?? 'standard') === 'compact' ? '3px' : '6px';
        $logo = base64_encode(file_get_contents(public_path('images/report-header.jpeg')));
        $head = implode('', array_map(fn ($key, $label) => '<th'.($this->isTotalColumn($key) ? ' class="total-column"' : '').'>'.$this->html(mb_strtoupper($label)).'</th>', array_keys($columns), array_values($columns)));
        $body = '';
        $expenseSummary = count($this->totalRows($totals)) === 3 && isset($columns['project_name']);
        foreach ($rows as $row) {
            $body .= $this->htmlRow($columns, $row, false, $expenseSummary ? 'project-row' : '');
        }
        foreach ($this->totalRows($totals) as $index => $totalRow) {
            $body .= $this->htmlRow($columns, $totalRow, true, $expenseSummary ? ['current', 'previous', 'month'][$index] : '');
        }
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 28px; } body { font-family: DejaVu Sans, sans-serif; color:#17283a; font-size:8px; }
            header { text-align:center; margin-bottom:15px; } header img { width:330px; height:auto; }
            h1 { font-size:14px; margin:7px 0 3px; } p { margin:2px 0; }
            table { border-collapse:collapse; width:100%; table-layout:fixed; margin-top:12px; }
            th { background:'.$headerColor.'; color:white; font-size:7px; padding:'.$cellPadding.'; overflow-wrap:anywhere; border:1px solid #aab6c2; text-align:center; }
            td { border:1px solid #aab6c2; padding:'.$cellPadding.'; text-align:center; overflow-wrap:anywhere; }
            tr.project-row td:first-child { font-weight:bold; }
            tr.total td { font-weight:bold; border-top:2px solid #1a2b3c; background:#f2f4f7; }
            th.total-column, td.total-column { background:#e9f7ed; color:#176638; }
            table.summary th.total-column, table.summary td.total-column, tr.total.current td.total-column { background:#f2f4f7; color:#17283a; }
            tr.total.previous td, tr.total.previous td.total-column { background:#e9f7ed; color:#176638; }
            tr.total.month td, tr.total.month td.total-column { background:#fdeaea; color:#a52525; }
            thead { display:table-header-group; } tr { page-break-inside:avoid; }
            </style></head><body><header><img src="data:image/jpeg;base64,'.$logo.'"><h1>'.$this->html($title).'</h1>
            <p>As of '.now()->format('F j, Y').'</p><p>'.$this->html($scope).'</p></header>
            <table'.($expenseSummary ? ' class="summary"' : '').'><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table></body></html>';
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a3', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();
        return $dompdf->output();
    }

    private function htmlRow(array $columns, array $row, bool $total = false, string $rowClass = ''): string
    {
        $cells = '';
        foreach (array_keys($columns) as $key) {
            $value = $row[$key] ?? '';
            $number = is_numeric($value) && ! in_array($key, ['project_name', 'item_name', 'category_name', 'supplier_name', 'unit_name', 'stock_status'], true);
            $display = $number ? number_format((float) $value, $key === 'item_id' ? 0 : 2) : $value;
            $cells .= '<td'.($this->isTotalColumn($key) ? ' class="total-column"' : '').'>'.$this->html($display).'</td>';
        }
        return '<tr'.($total || $rowClass !== '' ? ' class="'.trim(($total ? 'total ' : '').$rowClass).'"' : '').'>'.$cells.'</tr>';
    }

    private function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function xlsx(string $title, string $scope, array $columns, array $rows, ?array $totals, array $design): string
    {
        $headerRgb = match ($design['header_color'] ?? 'navy') { 'orange' => 'FFC96C00', 'green' => 'FF176638', default => 'FF1A2B3C' };
        $compact = ($design['table_spacing'] ?? 'standard') === 'compact';
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
            $stylesXml = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd"/></numFmts>'
                .'<fonts count="5"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="13"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FF176638"/><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFA52525"/><sz val="10"/><name val="Arial"/></font></fonts>'
                .'<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1A2B3C"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE9F7ED"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F4F7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFDEAEA"/><bgColor indexed="64"/></patternFill></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="19"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="164" fontId="3" fillId="3" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/>'
                .'<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="0" fontId="1" fillId="4" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="164" fontId="1" fillId="4" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/>'
                .'<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="164" fontId="3" fillId="3" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/>'
                .'<xf numFmtId="0" fontId="4" fillId="5" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="164" fontId="4" fillId="5" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/>'
                .'<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1"/>'
                .'<xf numFmtId="164" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/>'
                .'<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1"/>'
                .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
            $stylesXml = str_replace('FF1A2B3C', $headerRgb, $stylesXml);
            $stylesXml = str_replace('<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>',
                '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFAAB6C2"/></left><right style="thin"><color rgb="FFAAB6C2"/></right><top style="thin"><color rgb="FFAAB6C2"/></top><bottom style="thin"><color rgb="FFAAB6C2"/></bottom><diagonal/></border></borders>', $stylesXml);
            $stylesXml = preg_replace_callback('/<xf\b[^>]*\bxfId="0"[^>]*\/>/', static function ($match) {
                $xf = str_replace('borderId="0"', 'borderId="1" applyBorder="1"', $match[0]);
                return substr($xf, 0, -2).'><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
            }, $stylesXml);
            $zip->addFromString('xl/styles.xml', $stylesXml);

            $columnCount = count($columns);
            $lastColumn = $this->excelColumn($columnCount);
            $xmlRows = '<row r="1" ht="32" customHeight="1"/><row r="2" ht="32" customHeight="1"/><row r="3" ht="32" customHeight="1"/>';
            $xmlRows .= '<row r="5">'.$this->xlsxCell('A5', $title, 1).'</row>';
            $xmlRows .= '<row r="6">'.$this->xlsxCell('A6', 'As of '.now()->format('F j, Y')).'</row>';
            $xmlRows .= '<row r="7">'.$this->xlsxCell('A7', $scope, 1).'</row>';
            $expenseSummary = count($this->totalRows($totals)) === 3 && isset($columns['project_name']);
            $headerCells = '';
            $index = 0;
            foreach ($columns as $key => $label) {
                $headerCells .= $this->xlsxCell($this->excelColumn($index + 1).'9', mb_strtoupper($label),
                    $this->isTotalColumn($key) ? ($expenseSummary ? 16 : 7) : 2);
                $index++;
            }
            $xmlRows .= '<row r="9" ht="'.($compact ? '30' : '42').'" customHeight="1">'.$headerCells.'</row>';
            $rowNumber = 10;
            foreach ($rows as $row) {
                $xmlRows .= $this->xlsxRow($rowNumber++, $columns, $row, false, '', $expenseSummary);
            }
            foreach ($this->totalRows($totals) as $index => $totalRow) {
                $xmlRows .= $this->xlsxRow($rowNumber++, $columns, $totalRow, true,
                    $expenseSummary ? ['current', 'previous', 'month'][$index] : '');
            }
            $widths = '<cols>';
            foreach (array_keys($columns) as $index => $key) {
                $widths .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.($index === 0 ? 32 : 20).'" customWidth="1"/>';
            }
            $widths .= '</cols>';
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="'.($compact ? '13' : '17').'"/>'.$widths.'<sheetData>'.$xmlRows.'</sheetData>'
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

    private function xlsxRow(int $number, array $columns, array $row, bool $total = false, string $balanceType = '', bool $expenseSummary = false): string
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
            $style = match ($balanceType) {
                'current' => $isNumber ? 11 : 10,
                'previous' => $isNumber ? 13 : 12,
                'month' => $isNumber ? 15 : 14,
                default => $this->isTotalColumn($key) ? ($expenseSummary ? ($isNumber ? 17 : 18) : ($isNumber ? 8 : 9))
                    : ($isNumber ? ($key === 'item_id' ? 6 : ($total ? 4 : 3))
                        : ($total || ($expenseSummary && $key === 'project_name') ? 1 : 0)),
            };
            $cells .= $this->xlsxCell($this->excelColumn($index + 1).$number, $value,
                $style, ! $isNumber);
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

    private function isTotalColumn(string $key): bool
    {
        return $key === 'total' || str_starts_with($key, 'total_');
    }
}
