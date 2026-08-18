<?php
/**
 * Parser + importer for the "DAILY REPORT FO PUMP ASSY" (F-FIP-03) workbook.
 * The source file has one sheet per month, each holding repeated day-blocks:
 *   Date : | <serial> | Employee: | <n> | Working time : | <minutes> | <shift>
 *   NO | FO PUMP PRODUCTION (Model,Quantity) | TO ASSEMBLY LINE (...) | TO EXPORT YSP (...)
 *   up to 9 numbered rows
 * This reads the raw OOXML directly (ZipArchive + SimpleXML) — no external
 * library needed, matching the rest of this app's xlsx handling.
 */

function fopump_col_to_index(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) $index = $index * 26 + (ord($letters[$i]) - 64);
    return $index;
}

function fopump_excel_date_to_ymd($serial): ?string
{
    if (!is_numeric($serial)) return null;
    $unix = ((float)$serial - 25569) * 86400;
    return gmdate('Y-m-d', (int)$unix);
}

/**
 * Parse the uploaded workbook into a list of day-blocks:
 * ['date'=>'Y-m-d', 'employee'=>string, 'working_time'=>string, 'shift'=>string,
 *  'lines'=>[['no'=>int,'production'=>['model'=>,'qty'=>],'assembly'=>[...],'export'=>[...]], ...]]
 * Blocks with zero filled lines (blank template rows for future dates) are skipped.
 */
function fopump_parse_workbook(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not open the uploaded file.');
    }

    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = @simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string)$si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) $text .= (string)$r->t;
                    $shared[] = $text;
                }
            }
        }
    }

    $wbXml = @simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $sheetCount = $wbXml ? count($wbXml->sheets->sheet) : 1;

    $allDays = [];

    for ($si = 0; $si < max(1, $sheetCount); $si++) {
        $sheetXml = $zip->getFromName('xl/worksheets/sheet' . ($si + 1) . '.xml');
        if ($sheetXml === false) continue;
        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false || !isset($sheet->sheetData)) continue;

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowIndex = (int)$row['r'];
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                preg_match('/^([A-Z]+)/', $ref, $mm);
                $col = fopump_col_to_index($mm[1] ?? 'A');
                $type = (string)$c['t'];
                if ($type === 's') {
                    $val = $shared[(int)$c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = isset($c->is->t) ? (string)$c->is->t : '';
                } else {
                    $val = isset($c->v) ? (string)$c->v : '';
                }
                $cells[$col] = trim((string)$val);
            }
            $rows[$rowIndex] = $cells;
        }
        if (!$rows) continue;
        ksort($rows);
        $maxRow = max(array_keys($rows));

        for ($r = 1; $r <= $maxRow; $r++) {
            if (!isset($rows[$r])) continue;
            if (($rows[$r][1] ?? '') !== 'Date :') continue;

            $dateSerial = $rows[$r][2] ?? '';
            $ymd = fopump_excel_date_to_ymd($dateSerial);
            if (!$ymd) continue;

            $employee = $rows[$r][4] ?? '';
            $workingTime = $rows[$r][6] ?? '';
            $shift = $rows[$r][7] ?? '';

            $lines = [];
            for ($dr = $r + 3; $dr <= $r + 11; $dr++) {
                if (!isset($rows[$dr])) continue;
                $no = $rows[$dr][1] ?? '';
                if ($no === '' || !is_numeric($no)) continue;
                $prodModel = $rows[$dr][2] ?? '';
                $prodQty   = $rows[$dr][3] ?? '';
                $assyModel = $rows[$dr][4] ?? '';
                $assyQty   = $rows[$dr][5] ?? '';
                $expModel  = $rows[$dr][6] ?? '';
                $expQty    = $rows[$dr][7] ?? '';
                if ($prodModel !== '' || $assyModel !== '' || $expModel !== '') {
                    $lines[] = [
                        'no' => (int)$no,
                        'production' => ['model' => $prodModel, 'qty' => $prodQty],
                        'assembly'   => ['model' => $assyModel, 'qty' => $assyQty],
                        'export'     => ['model' => $expModel, 'qty' => $expQty],
                    ];
                }
            }

            if ($lines) {
                $allDays[] = [
                    'date' => $ymd,
                    'employee' => $employee,
                    'working_time' => $workingTime,
                    'shift' => $shift,
                    'lines' => $lines,
                ];
            }
        }
    }
    $zip->close();

    return $allDays;
}

/**
 * Upsert parsed day-blocks into t_fopump_header / t_fopump_line.
 * Matches by (department_id, tanggal) — existing dates are replaced (lines
 * deleted and reinserted), new dates are created.
 */
function fopump_import_days(PDO $pdo, int $department_id, array $days): array
{
    $res = ['created' => 0, 'updated' => 0, 'lines' => 0];

    $selH = $pdo->prepare('SELECT id FROM t_fopump_header WHERE department_id = ? AND tanggal = ?');
    $insH = $pdo->prepare(
        'INSERT INTO t_fopump_header (department_id, tanggal, employee_count, working_minutes, shift_label, status)
         VALUES (?, ?, ?, ?, ?, \'submitted\')'
    );
    $updH = $pdo->prepare(
        'UPDATE t_fopump_header SET employee_count=?, working_minutes=?, shift_label=?, status=\'submitted\' WHERE id=?'
    );
    $delL = $pdo->prepare('DELETE FROM t_fopump_line WHERE header_id = ?');
    $insL = $pdo->prepare(
        'INSERT INTO t_fopump_line (header_id, line_no, production_model, production_qty, assembly_model, assembly_qty, export_model, export_qty)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($days as $d) {
        $employee = is_numeric($d['employee']) ? (int)$d['employee'] : null;
        $workingTime = is_numeric($d['working_time']) ? (int)$d['working_time'] : null;
        $shift = $d['shift'] !== '' && $d['shift'] !== '0' ? $d['shift'] : null;

        $selH->execute([$department_id, $d['date']]);
        $hid = $selH->fetchColumn();
        if ($hid) {
            $updH->execute([$employee, $workingTime, $shift, $hid]);
            $delL->execute([$hid]);
            $res['updated']++;
        } else {
            $insH->execute([$department_id, $d['date'], $employee, $workingTime, $shift]);
            $hid = (int)$pdo->lastInsertId();
            $res['created']++;
        }

        foreach ($d['lines'] as $line) {
            $pQty = is_numeric($line['production']['qty']) ? (int)$line['production']['qty'] : null;
            $aQty = is_numeric($line['assembly']['qty']) ? (int)$line['assembly']['qty'] : null;
            $eQty = is_numeric($line['export']['qty']) ? (int)$line['export']['qty'] : null;
            $insL->execute([
                $hid, $line['no'],
                $line['production']['model'] !== '' ? $line['production']['model'] : null, $pQty,
                $line['assembly']['model'] !== '' ? $line['assembly']['model'] : null, $aQty,
                $line['export']['model'] !== '' ? $line['export']['model'] : null, $eQty,
            ]);
            $res['lines']++;
        }
    }

    return $res;
}
