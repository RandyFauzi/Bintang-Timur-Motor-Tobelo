<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExcelExportService
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @param  list<string>  $meta
     */
    public function download(
        string $filename,
        string $title,
        array $headers,
        iterable $rows,
        string $subtitle = '',
        array $meta = []
    ): StreamedResponse {
        return response()->streamDownload(function () use ($title, $headers, $rows, $subtitle, $meta): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'bintang_xlsx_');
            $zip = new ZipArchive;
            $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $zip->addFromString('[Content_Types].xml', $this->getContentTypesXml());
            $zip->addFromString('_rels/.rels', $this->getRootRelsXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRelsXml());
            $zip->addFromString('xl/workbook.xml', $this->getWorkbookXml());
            $zip->addFromString('xl/styles.xml', $this->getStylesXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->getSheetXml($title, $headers, $rows, $subtitle, $meta));

            $zip->close();

            $handle = fopen($tempFile, 'rb');
            fpassthru($handle);
            fclose($handle);
            @unlink($tempFile);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function getContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function getRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function getWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function getWorkbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Data Laporan" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    /**
     * Styles configuration:
     * - Fonts:
     *   0: Calibri 10pt (Normal)
     *   1: Calibri 16pt Bold White (Company Title)
     *   2: Calibri 12pt Bold #1E293B (Subtitle)
     *   3: Calibri 9pt Italic #64748B (Meta)
     *   4: Calibri 11pt Bold White (Table Header)
     *   5: Calibri 10pt Bold #1E293B (Summary Bold)
     * - Fills:
     *   0: None
     *   1: Gray125
     *   2: #1E293B (Dark Slate - Company Banner & Header)
     *   3: #F1F5F9 (Light Slate - Subtitle & Summary)
     *   4: #F8FAFC (Zebra row light)
     *   5: #2E5A1C (Brand Green)
     * - Borders:
     *   0: None
     *   1: Thin around (#CBD5E1)
     *   2: Top Thin + Bottom Double (Summary)
     * - XFs (Cell Formats):
     *   0: Default normal
     *   1: Company Title (font 1, fill 2, align center)
     *   2: Subtitle (font 2, fill 3, align center)
     *   3: Meta (font 3, align center)
     *   4: Table Header (font 4, fill 2, border 1, align center)
     *   5: Data Text Left (font 0, border 1, align left)
     *   6: Data Text Left Zebra (font 0, fill 4, border 1, align left)
     *   7: Data Text Center (font 0, border 1, align center)
     *   8: Data Text Center Zebra (font 0, fill 4, border 1, align center)
     *   9: Data Number Right (font 0, border 1, numFmtId 3, align right)
     *   10: Data Number Right Zebra (font 0, fill 4, border 1, numFmtId 3, align right)
     *   11: Data Currency Right (font 0, border 1, numFmtId 164, align right)
     *   12: Data Currency Right Zebra (font 0, fill 4, border 1, numFmtId 164, align right)
     *   13: Summary Label (font 5, fill 3, border 2, align center)
     *   14: Summary Number (font 5, fill 3, border 2, numFmtId 3, align right)
     *   15: Summary Currency (font 5, fill 3, border 2, numFmtId 164, align right)
     */
    private function getStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1">'
            .'<numFmt numFmtId="164" formatCode="&quot;Rp &quot;#,##0"/>'
            .'</numFmts>'
            .'<fonts count="6">'
            .'<font><sz val="10"/><name val="Calibri"/><color rgb="FF1E293B"/></font>'
            .'<font><b/><sz val="16"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            .'<font><b/><sz val="12"/><name val="Calibri"/><color rgb="FF1E293B"/></font>'
            .'<font><i/><sz val="9"/><name val="Calibri"/><color rgb="FF64748B"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            .'<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF0F172A"/></font>'
            .'</fonts>'
            .'<fills count="6">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF2E5A1C"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="3">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFCBD5E1"/></left>'
            .'<right style="thin"><color rgb="FFCBD5E1"/></right>'
            .'<top style="thin"><color rgb="FFCBD5E1"/></top>'
            .'<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            .'</border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFCBD5E1"/></left>'
            .'<right style="thin"><color rgb="FFCBD5E1"/></right>'
            .'<top style="thin"><color rgb="FF94A3B8"/></top>'
            .'<bottom style="double"><color rgb="FF0F172A"/></bottom>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            .'</cellStyleXfs>'
            .'<cellXfs count="16">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0: Default
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 1: Title
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 2: Subtitle
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 3: Meta
            .'<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 4: Header
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' // 5: Text Left
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' // 6: Text Left Zebra
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 7: Text Center
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 8: Text Center Zebra
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 9: Number Right
            .'<xf numFmtId="3" fontId="0" fillId="4" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 10: Number Right Zebra
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 11: Currency Right
            .'<xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 12: Currency Right Zebra
            .'<xf numFmtId="0" fontId="5" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 13: Summary Label
            .'<xf numFmtId="3" fontId="5" fillId="3" borderId="2" xfId="0" applyFont="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 14: Summary Number
            .'<xf numFmtId="164" fontId="5" fillId="3" borderId="2" xfId="0" applyFont="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 15: Summary Currency
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function getSheetXml(string $title, array $headers, iterable $rows, string $subtitle, array $meta): string
    {
        $colCount = max(count($headers), 1);
        $lastColLetter = $this->getColumnLetter($colCount);

        // Convert rows to array to calculate widths and totals
        $rowList = [];
        $colWidths = array_fill(1, $colCount, 12);

        foreach ($headers as $idx => $h) {
            $colWidths[$idx + 1] = max($colWidths[$idx + 1], mb_strlen((string) $h) + 4);
        }

        $sums = array_fill(0, $colCount, null);

        foreach ($rows as $row) {
            $rowArray = array_values((array) $row);
            $rowList[] = $rowArray;
            foreach ($rowArray as $cIdx => $val) {
                $colNum = $cIdx + 1;
                $strVal = (string) $val;
                $colWidths[$colNum] = min(60, max($colWidths[$colNum], mb_strlen($strVal) + 3));

                if (is_numeric($val) && ! in_array($headers[$cIdx] ?? '', ['SKU', 'Barcode', 'ID Retur', 'Invoice', 'Telepon', 'Jam'], true)) {
                    $sums[$cIdx] = ($sums[$cIdx] ?? 0) + (float) $val;
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Column widths
        $xml .= '<cols>';
        foreach ($colWidths as $colNum => $width) {
            $xml .= '<col min="'.$colNum.'" max="'.$colNum.'" width="'.$width.'" customWidth="1"/>';
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';

        $currentRow = 1;

        $mergedRows = [];

        // Row 1: Company Header
        $xml .= '<row r="'.$currentRow.'" ht="36" customHeight="1">';
        $xml .= '<c r="A'.$currentRow.'" s="1" t="inlineStr"><is><t>BINTANG TIMUR MOTOR TOBELO</t></is></c>';
        for ($c = 2; $c <= $colCount; $c++) {
            $xml .= '<c r="'.$this->getColumnLetter($c).$currentRow.'" s="1"/>';
        }
        $xml .= '</row>';
        $mergedRows[] = $currentRow;
        $currentRow++;

        // Row 2: Title
        $xml .= '<row r="'.$currentRow.'" ht="26" customHeight="1">';
        $xml .= '<c r="A'.$currentRow.'" s="2" t="inlineStr"><is><t>'.htmlspecialchars($title, ENT_XML1).'</t></is></c>';
        for ($c = 2; $c <= $colCount; $c++) {
            $xml .= '<c r="'.$this->getColumnLetter($c).$currentRow.'" s="2"/>';
        }
        $xml .= '</row>';
        $mergedRows[] = $currentRow;
        $currentRow++;

        // Row 3: Subtitle (if provided)
        if (! empty($subtitle)) {
            $xml .= '<row r="'.$currentRow.'" ht="22" customHeight="1">';
            $xml .= '<c r="A'.$currentRow.'" s="3" t="inlineStr"><is><t>'.htmlspecialchars($subtitle, ENT_XML1).'</t></is></c>';
            for ($c = 2; $c <= $colCount; $c++) {
                $xml .= '<c r="'.$this->getColumnLetter($c).$currentRow.'" s="3"/>';
            }
            $xml .= '</row>';
            $mergedRows[] = $currentRow;
            $currentRow++;
        }

        // Meta info
        $metaParts = array_merge([
            'Waktu Ekspor: '.now()->translatedFormat('d F Y, H:i').' WIT',
            'Total Data: '.count($rowList).' baris',
        ], $meta);
        $metaText = implode('  |  ', $metaParts);
        $xml .= '<row r="'.$currentRow.'" ht="20" customHeight="1">';
        $xml .= '<c r="A'.$currentRow.'" s="3" t="inlineStr"><is><t>'.htmlspecialchars($metaText, ENT_XML1).'</t></is></c>';
        for ($c = 2; $c <= $colCount; $c++) {
            $xml .= '<c r="'.$this->getColumnLetter($c).$currentRow.'" s="3"/>';
        }
        $xml .= '</row>';
        $mergedRows[] = $currentRow;
        $currentRow++;

        // Spacer
        $xml .= '<row r="'.$currentRow.'" ht="10" customHeight="1"></row>';
        $currentRow++;

        // Row 5: Table Headers
        $headerRow = $currentRow;
        $xml .= '<row r="'.$headerRow.'" ht="28" customHeight="1">';
        foreach ($headers as $idx => $header) {
            $colLetter = $this->getColumnLetter($idx + 1);
            $xml .= '<c r="'.$colLetter.$headerRow.'" s="4" t="inlineStr"><is><t>'.htmlspecialchars((string) $header, ENT_XML1).'</t></is></c>';
        }
        $xml .= '</row>';
        $currentRow++;

        // Data Rows
        $startDataRow = $currentRow;
        foreach ($rowList as $rIdx => $rowValues) {
            $isZebra = ($rIdx % 2 === 1);
            $xml .= '<row r="'.$currentRow.'" ht="22" customHeight="1">';
            foreach ($rowValues as $cIdx => $val) {
                $colLetter = $this->getColumnLetter($cIdx + 1);
                $headerName = $headers[$cIdx] ?? '';
                $cellRef = $colLetter.$currentRow;

                if ($val === null || $val === '') {
                    $style = $isZebra ? 8 : 7;
                    $xml .= '<c r="'.$cellRef.'" s="'.$style.'" t="inlineStr"><is><t>–</t></is></c>';
                } elseif ($this->isCurrencyColumn($headerName) && is_numeric($val)) {
                    $style = $isZebra ? 12 : 11;
                    $xml .= '<c r="'.$cellRef.'" s="'.$style.'"><v>'.((float) $val).'</v></c>';
                } elseif ($this->isNumericColumn($headerName) && is_numeric($val)) {
                    $style = $isZebra ? 10 : 9;
                    $xml .= '<c r="'.$cellRef.'" s="'.$style.'"><v>'.((float) $val).'</v></c>';
                } elseif ($this->isCenteredColumn($headerName)) {
                    $style = $isZebra ? 8 : 7;
                    $xml .= '<c r="'.$cellRef.'" s="'.$style.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $val, ENT_XML1).'</t></is></c>';
                } else {
                    $style = $isZebra ? 6 : 5;
                    $safeStr = (string) $val;
                    if (preg_match('/^[=+@\t\r-]/', $safeStr)) {
                        $safeStr = "'".$safeStr;
                    }
                    $xml .= '<c r="'.$cellRef.'" s="'.$style.'" t="inlineStr"><is><t>'.htmlspecialchars($safeStr, ENT_XML1).'</t></is></c>';
                }
            }
            $xml .= '</row>';
            $currentRow++;
        }
        $endDataRow = $currentRow - 1;

        // Summary Row (Footer)
        $hasAnySum = count(array_filter($sums, fn ($s) => $s !== null)) > 0;
        if ($hasAnySum && count($rowList) > 0) {
            $xml .= '<row r="'.$currentRow.'" ht="26" customHeight="1">';
            $firstSumCol = null;
            foreach ($sums as $cIdx => $s) {
                if ($s !== null) {
                    $firstSumCol = $cIdx + 1;
                    break;
                }
            }

            foreach ($headers as $cIdx => $header) {
                $colNum = $cIdx + 1;
                $colLetter = $this->getColumnLetter($colNum);
                $cellRef = $colLetter.$currentRow;

                if ($colNum < ($firstSumCol ?? 2)) {
                    $label = ($colNum === 1) ? 'TOTAL KESELURUHAN' : '';
                    $xml .= '<c r="'.$cellRef.'" s="13" t="inlineStr"><is><t>'.$label.'</t></is></c>';
                } elseif ($sums[$cIdx] !== null) {
                    $headerName = $headers[$cIdx] ?? '';
                    $sumStyle = $this->isCurrencyColumn($headerName) ? 15 : 14;
                    $formula = 'SUM('.$colLetter.$startDataRow.':'.$colLetter.$endDataRow.')';
                    $xml .= '<c r="'.$cellRef.'" s="'.$sumStyle.'"><f>'.$formula.'</f><v>'.$sums[$cIdx].'</v></c>';
                } else {
                    $xml .= '<c r="'.$cellRef.'" s="13" t="inlineStr"><is><t>–</t></is></c>';
                }
            }
            $xml .= '</row>';
            $currentRow++;
        }

        $xml .= '</sheetData>';

        // Merged Cells for Titles and Headers
        if (! empty($mergedRows)) {
            $xml .= '<mergeCells count="'.count($mergedRows).'">';
            foreach ($mergedRows as $mRow) {
                $xml .= '<mergeCell ref="A'.$mRow.':'.$lastColLetter.$mRow.'"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '</worksheet>';

        return $xml;
    }

    private function getColumnLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $mod = ($colNumber - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $colNumber = (int) (($colNumber - $mod) / 26);
        }

        return $letter;
    }

    private function isCurrencyColumn(string $header): bool
    {
        return (bool) preg_match('/(modal|harga|subtotal|total|diskon|pajak|kembalian|uang)/i', $header);
    }

    private function isNumericColumn(string $header): bool
    {
        return (bool) preg_match('/(stok|jumlah|unit|qty|kuantitas)/i', $header);
    }

    private function isCenteredColumn(string $header): bool
    {
        return (bool) preg_match('/(sku|barcode|tanggal|tgl|jam|status|role|tipe|metode|id)/i', $header);
    }
}
