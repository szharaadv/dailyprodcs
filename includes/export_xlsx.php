<?php
/**
 * Native .xlsx export writers (PhpSpreadsheet) — a real spreadsheet with a
 * proper embedded logo, real numeric cells, borders and print setup, so the
 * file prints tidily (A4 landscape, fit to page width, horizontally centered)
 * instead of the HTML-as-.xls fallback in export_checksheet.php.
 */

require_once __DIR__ . '/excel_lib.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/** Which section routes have a native .xlsx writer (others fall back to HTML). */
function xlsx_export_supported(string $route): bool
{
    return $route === 'fopump_list.php' && excel_available();
}

/** Dispatch to the right native .xlsx writer and stream it. */
function export_section_xlsx(PDO $pdo, array $section, int $month, int $year, string $title, string $monthLabel): void
{
    switch ($section['route']) {
        case 'fopump_list.php':
            export_fopump_xlsx($pdo, $section, $month, $year, $title, $monthLabel);
            return;
    }
}

/** Draw the shared branded header (logo + title + Bulan + doc box) on A1:G4. */
function _xlsx_header($sheet, array $section, string $title, string $monthLabel): void
{
    // Logo box A1:B4 — the logo is embedded as a real drawing sized to sit
    // inside the two-column box, so it never spills onto the title.
    $sheet->mergeCells('A1:B4');
    $sheet->getStyle('A1:B4')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
    for ($r = 1; $r <= 4; $r++) $sheet->getRowDimension($r)->setRowHeight(17);

    $logo = null;
    foreach (['png', 'jpg', 'jpeg'] as $ext) {
        $p = __DIR__ . '/../assets/img/logo.' . $ext;
        if (is_file($p)) { $logo = $p; break; }
    }
    if ($logo) {
        $d = new Drawing();
        $d->setName('Logo');
        $d->setPath($logo);
        $d->setHeight(56);           // ~4 rows tall; width follows aspect ratio
        $d->setCoordinates('A1');
        $d->setOffsetX(28);
        $d->setOffsetY(6);
        $d->setWorksheet($sheet);
    }

    // Title C1:E2 and "Bulan : …" C3:E4.
    $sheet->mergeCells('C1:E2');
    $sheet->setCellValue('C1', $title);
    $sheet->getStyle('C1')->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('C1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $sheet->mergeCells('C3:E4');
    $sheet->setCellValue('C3', 'Bulan : ' . $monthLabel);
    $sheet->getStyle('C3')->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle('C3')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    // Doc box F1:G4.
    $box = [
        ['No. Doc', $section['doc_no'] ?? ''],
        ['Revisi',  $section['doc_rev'] ?? ''],
        ['Tgl',     $section['doc_date'] ?? ''],
        ['Halaman', '1 / 1'],
    ];
    foreach ($box as $i => [$label, $val]) {
        $r = $i + 1;
        $sheet->setCellValue('F' . $r, $label);
        $sheet->setCellValueExplicit('G' . $r, (string)$val, DataType::TYPE_STRING);
        $sheet->getStyle('F' . $r)->getFont()->setBold(true);
    }
    $sheet->getStyle('F1:G4')->getFont()->setSize(9);
    $sheet->getStyle('F1:G4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('F1:G4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    // Frame the whole header as one boxed form kop: a thin outline around the
    // title and "Bulan" cells (so the middle no longer floats between the
    // bordered logo and doc boxes) and a heavier outer border around it all.
    $sheet->getStyle('C1:E2')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('C3:E4')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A1:G4')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
}

/** Apply the page/print setup common to every native report. */
function _xlsx_print_setup($sheet, string $lastCol, int $lastRow): void
{
    $ps = $sheet->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
       ->setPaperSize(PageSetup::PAPERSIZE_A4)
       ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0)
       ->setHorizontalCentered(true);
    $ps->setPrintArea("A1:{$lastCol}{$lastRow}");
    $ps->setRowsToRepeatAtTopByStartAndEnd(1, 4); // reprint header on every page
    $sheet->setPrintGridlines(false);
    $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.35)->setRight(0.35)
        ->setHeader(0.2)->setFooter(0.2);
}

/** FO Pump Daily Production Report — production/assembly/export per line. */
function export_fopump_xlsx(PDO $pdo, array $section, int $month, int $year, string $title, string $monthLabel): void
{
    excel_available(); // ensure the PhpSpreadsheet autoloader is loaded
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Report');

    foreach (['A' => 6, 'B' => 22, 'C' => 9, 'D' => 24, 'E' => 9, 'F' => 22, 'G' => 9] as $c => $w) {
        $sheet->getColumnDimension($c)->setWidth($w);
    }

    _xlsx_header($sheet, $section, $title, $monthLabel);

    $hq = $pdo->prepare(
        "SELECT h.id, h.tanggal, h.created_at, op.name AS operator
         FROM t_fopump_header h LEFT JOIN m_user op ON op.id=h.operator_id
         WHERE h.department_id=? AND h.status='submitted' AND MONTH(h.tanggal)=? AND YEAR(h.tanggal)=?
         ORDER BY h.tanggal, h.id"
    );
    $hq->execute([$section['department_id'], $month, $year]);
    $lq = $pdo->prepare(
        "SELECT line_no, production_model, production_qty, assembly_model, assembly_qty, export_model, export_qty
         FROM t_fopump_line WHERE header_id=? ORDER BY line_no"
    );
    $headers = $hq->fetchAll();

    $row = 6;
    if (!$headers) {
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'Tidak ada data untuk bulan ini.');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
    }

    foreach ($headers as $h) {
        // Banner.
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'Operator: ' . ($h['operator'] ?? '-')
            . '     |     Tanggal: ' . $h['tanggal'] . '     |     Submitted: ' . $h['created_at']);
        $bs = $sheet->getStyle("A{$row}");
        $bs->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $bs->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2C3E50');
        $bs->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $sheet->getRowDimension($row)->setRowHeight(19);
        $row++;

        // Column header.
        $labels = ['No', 'Production Model', 'Qty', 'Assembly Model', 'Qty', 'Export Model', 'Qty'];
        $c = 'A';
        foreach ($labels as $l) { $sheet->setCellValue($c . $row, $l); $c++; }
        $hs = $sheet->getStyle("A{$row}:G{$row}");
        $hs->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $hs->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1A9E7A');
        $hs->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $hs->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        $lq->execute([$h['id']]);
        $lines = $lq->fetchAll();
        $tp = 0; $ta = 0; $tx = 0;
        $firstRow = $row;
        foreach ($lines as $l) {
            $tp += (int)$l['production_qty']; $ta += (int)$l['assembly_qty']; $tx += (int)$l['export_qty'];
            $sheet->setCellValueExplicit("A{$row}", (int)$l['line_no'], DataType::TYPE_NUMERIC);
            _xlsx_pair($sheet, "B{$row}", "C{$row}", $l['production_model'], $l['production_qty']);
            _xlsx_pair($sheet, "D{$row}", "E{$row}", $l['assembly_model'], $l['assembly_qty']);
            _xlsx_pair($sheet, "F{$row}", "G{$row}", $l['export_model'], $l['export_qty']);
            $row++;
        }
        if (!$lines) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada baris.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // Totals.
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValueExplicit("C{$row}", $tp, DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("E{$row}", $ta, DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("G{$row}", $tx, DataType::TYPE_NUMERIC);
        $ts = $sheet->getStyle("A{$row}:G{$row}");
        $ts->getFont()->setBold(true);
        $ts->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');

        // Borders + alignment across the record's grid.
        $grid = $sheet->getStyle("A{$firstRow}:G{$row}");
        $grid->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (['A', 'C', 'E', 'G'] as $col) {
            $sheet->getStyle("{$col}{$firstRow}:{$col}{$row}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $row += 2; // spacer between records
    }

    // Signatures.
    $row++;
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->mergeCells("E{$row}:G{$row}");
    $sheet->setCellValue("A{$row}", 'Checked By,');
    $sheet->setCellValue("E{$row}", 'Approved By,');
    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
    $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row += 2;
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->mergeCells("E{$row}:G{$row}");
    $sheet->setCellValue("A{$row}", '(                    )');
    $sheet->setCellValue("E{$row}", '(                    )');
    $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    _xlsx_print_setup($sheet, 'G', $row);

    $fname = preg_replace('/[^A-Za-z0-9_\- ]/', '', $section['name']) . ' ' . $monthLabel . '.xlsx';
    excel_download($spreadsheet, $fname);
}

/** Write a model+qty pair: model text left, qty as a real number — but leave
 *  both blank when there's no model (matches the paper form's empty rows). */
function _xlsx_pair($sheet, string $modelCell, string $qtyCell, $model, $qty): void
{
    $model = trim((string)$model);
    if ($model === '') return; // leave the pair blank
    $sheet->setCellValueExplicit($modelCell, $model, DataType::TYPE_STRING);
    $sheet->setCellValueExplicit($qtyCell, (int)$qty, DataType::TYPE_NUMERIC);
}
