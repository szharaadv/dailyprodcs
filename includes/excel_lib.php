<?php
/**
 * Excel (.xlsx) export helpers built on PhpSpreadsheet, with the branded report
 * header (logo + title + "Bulan : …" + No.Doc/Revisi/Tgl/Halaman box).
 *
 * PhpSpreadsheet must be installed (composer require phpoffice/phpspreadsheet,
 * which creates vendor/, or drop it into lib/). excel_available() reports
 * whether it's loadable so callers can show a friendly message instead of a
 * broken download.
 *
 * Logo: put the report logo at assets/img/logo.png (PNG). If absent, the
 * header still renders, just without the image.
 */

function excel_available(): bool
{
    if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) return true;
    // PhpSpreadsheet 5.x needs PHP 8.1+ and these extensions. On an older or
    // under-provisioned server, don't even load it — just report unavailable so
    // callers fall back to the plain HTML export instead of fatal-erroring.
    if (PHP_VERSION_ID < 80100) return false;
    foreach (['zip', 'xml', 'mbstring'] as $ext) {
        if (!extension_loaded($ext)) return false;
    }
    foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../lib/phpspreadsheet/autoload.php'] as $f) {
        if (is_file($f)) { require_once $f; if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) return true; }
    }
    return false;
}

/** Bulan Indonesia. */
function excel_month_label(int $month, int $year): string
{
    $names = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
        'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return ($names[$month] ?? $month) . ' ' . $year;
}

/**
 * Draw the branded header on $sheet. $title e.g. "PAINTING MONTHLY CHECK SHEET
 * REPORT"; $doc = ['no'=>, 'rev'=>, 'date'=>]. $lastCol = right-most data column
 * letter so the title band spans the sheet. Returns the first free data row.
 */
function excel_render_header($sheet, string $title, string $monthLabel, array $doc, string $lastCol = 'E'): int
{
    // Doc box sits in the two columns after the title band.
    $docLabelCol = chr(ord($lastCol) + 1); // e.g. F
    $docValCol   = chr(ord($lastCol) + 2); // e.g. G

    // Title band A1:{lastCol}2
    $sheet->mergeCells("A1:{$lastCol}2");
    $sheet->setCellValue('A1', $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    // "Bulan : …" A3:{lastCol}4
    $sheet->mergeCells("A3:{$lastCol}4");
    $sheet->setCellValue('A3', 'Bulan : ' . $monthLabel);
    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A3')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Logo (optional) — top-left over the title band.
    $logo = __DIR__ . '/../assets/img/logo.png';
    if (is_file($logo)) {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setPath($logo);
        $drawing->setHeight(48);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(6);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);
    }

    // Doc box (labels + values), rows 1-4.
    $box = [
        ['No. Doc', $doc['no'] ?? ''],
        ['Revisi',  $doc['rev'] ?? ''],
        ['Tgl',     $doc['date'] ?? ''],
        ['Halaman', '1 / 1'],
    ];
    foreach ($box as $i => [$label, $value]) {
        $row = $i + 1;
        $sheet->setCellValue($docLabelCol . $row, $label);
        $sheet->setCellValue($docValCol . $row, $value);
        $sheet->getStyle($docLabelCol . $row)->getFont()->setBold(true);
    }
    $sheet->getStyle("{$docLabelCol}1:{$docValCol}4")
        ->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    return 6; // first data row (leave row 5 as a spacer)
}

/** Stream a spreadsheet as an .xlsx download and end the request. */
function excel_download($spreadsheet, string $filename): void
{
    $filename = preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $filename);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
