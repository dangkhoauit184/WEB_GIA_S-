<?php
namespace App\Modules\Reporting\Features\Dashboard;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

final class SpreadsheetExport
{
    /**
     * Neutralize CSV/Excel formula injection (OWASP: leading =, +, -, @, tab, CR).
     */
    public static function sanitizeCell(mixed $value): string|int|float
    {
        if ($value === null) return '';
        if (is_int($value) || is_float($value)) return $value;
        if (is_bool($value)) return $value ? 'Có' : 'Không';
        $str = (string)$value;
        if ($str === '') return '';
        if (preg_match('/^[\t\r]/', $str) || preg_match('/^[\s]*[=+\-@]/', $str)) {
            return "'".$str;
        }
        return $str;
    }

    public static function csvLine(array $cells): string
    {
        $escaped = [];
        foreach ($cells as $cell) {
            $safe = (string)self::sanitizeCell($cell);
            $safe = str_replace('"', '""', $safe);
            if (strpbrk($safe, ",\"\r\n") !== false) {
                $escaped[] = '"'.$safe.'"';
            } else {
                $escaped[] = $safe;
            }
        }
        return implode(',', $escaped)."\r\n";
    }

    /**
     * UTF-8 with BOM CSV compatible with Vietnamese Microsoft Excel.
     */
    public static function toCsv(string $title, array $metaRows, array $headers, array $rows, array $detailTitleRow = [], array $detailHeaders = [], array $detailRows = []): string
    {
        $out = "\xEF\xBB\xBF";
        $out .= self::csvLine([$title]);
        foreach ($metaRows as $meta) {
            $out .= self::csvLine($meta);
        }
        $out .= "\r\n";
        $out .= self::csvLine($headers);
        foreach ($rows as $row) {
            $out .= self::csvLine($row);
        }
        if ($detailHeaders) {
            $out .= "\r\n";
            if ($detailTitleRow) $out .= self::csvLine($detailTitleRow);
            $out .= self::csvLine($detailHeaders);
            foreach ($detailRows as $row) {
                $out .= self::csvLine($row);
            }
        }
        return $out;
    }

    /**
     * Generate genuine multi-sheet .xlsx workbook via OpenSpout (PHP/Composer).
     */
    public static function toXlsx(
        string $title,
        array $metaRows,
        string $summarySheetName,
        array $summaryHeaders,
        array $summaryRows,
        string $detailSheetName,
        array $detailHeaders,
        array $detailRows
    ): string {
        $tmp = tempnam(sys_get_temp_dir(), 'educenter_rpt_');
        if ($tmp === false) {
            throw new \RuntimeException('Không tạo được tệp tạm để xuất Excel.');
        }
        $xlsxPath = $tmp.'.xlsx';
        @unlink($tmp);

        $titleStyle = (new Style())->withFontBold(true)->withFontSize(13);
        $headerStyle = (new Style())->withFontBold(true)->withFontSize(11);

        $writer = new Writer();
        try {
            $writer->openToFile($xlsxPath);

            $sheet1 = $writer->getCurrentSheet();
            $sheet1->setName(mb_substr($summarySheetName, 0, 31));
            $writer->addRow(Row::fromValuesWithStyle([self::sanitizeCell($title)], $titleStyle));
            foreach ($metaRows as $meta) {
                $writer->addRow(Row::fromValues(array_map([self::class, 'sanitizeCell'], $meta)));
            }
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValuesWithStyle(array_map([self::class, 'sanitizeCell'], $summaryHeaders), $headerStyle));
            foreach ($summaryRows as $row) {
                $writer->addRow(Row::fromValues(array_map([self::class, 'sanitizeCell'], $row)));
            }

            if ($detailHeaders) {
                $sheet2 = $writer->addNewSheetAndMakeItCurrent();
                $sheet2->setName(mb_substr($detailSheetName, 0, 31));
                $writer->addRow(Row::fromValuesWithStyle(array_map([self::class, 'sanitizeCell'], $detailHeaders), $headerStyle));
                foreach ($detailRows as $row) {
                    $writer->addRow(Row::fromValues(array_map([self::class, 'sanitizeCell'], $row)));
                }
            }

            $writer->close();
            $bytes = file_get_contents($xlsxPath);
            if ($bytes === false) {
                throw new \RuntimeException('Không đọc được tệp Excel vừa xuất.');
            }
            return $bytes;
        } finally {
            @unlink($xlsxPath);
        }
    }
}
