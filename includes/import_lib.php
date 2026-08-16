<?php
/**
 * Shared helpers for CSV data import.
 */

/**
 * Sniff the field delimiter from a CSV's first line. Handles the common case
 * of a CSV saved by a non-English-locale Excel (e.g. Indonesian), which
 * defaults to ';' instead of ',' as the list separator.
 */
function csv_detect_delimiter(string $firstLine): string
{
    $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
    $candidates = [',', ';', "\t"];
    $best = ',';
    $bestCount = 0;
    foreach ($candidates as $d) {
        $count = substr_count($firstLine, $d);
        if ($count > $bestCount) { $bestCount = $count; $best = $d; }
    }
    return $best;
}

/** Read a CSV file into rows of assoc arrays keyed by normalised header names. */
function csv_read(string $path): array
{
    $rows = [];
    if (($h = fopen($path, 'r')) === false) {
        return $rows;
    }

    if (fread($h, 3) !== "\xEF\xBB\xBF") {
        rewind($h);
    }

    $startPos = ftell($h);
    $firstLine = fgets($h);
    if ($firstLine === false) { fclose($h); return $rows; }
    $delimiter = csv_detect_delimiter($firstLine);
    fseek($h, $startPos);

    $header = null;
    while (($data = fgetcsv($h, 0, $delimiter)) !== false) {
        if ($header === null) {
            $header = array_map(fn($c) => strtolower(trim((string)$c)), $data);
            continue;
        }
        if (count(array_filter($data, fn($c) => trim((string)$c) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $col) {
            if ($col === '') continue;
            $row[$col] = isset($data[$i]) ? trim((string)$data[$i]) : '';
        }
        $rows[] = $row;
    }
    fclose($h);
    return $rows;
}

/** Convert an Excel column letter (A, B, ..., AA, ...) to a 0-based index. */
function xlsx_col_to_index(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

/**
 * Read the first worksheet of an .xlsx file into rows of assoc arrays keyed
 * by normalised header names (same shape as csv_read()). Uses only
 * ZipArchive + SimpleXML, both bundled with PHP, so no external library is
 * required.
 */
function xlsx_read(string $path): array
{
    $rows = [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return $rows;
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
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $shared[] = $text;
                }
            }
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    if ($zip->locateName($sheetPath) === false) {
        $sheetPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetPath = $name;
                break;
            }
        }
    }

    $sheetXml = $sheetPath ? $zip->getFromName($sheetPath) : false;
    $zip->close();
    if ($sheetXml === false) {
        return $rows;
    }

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false || !isset($sheet->sheetData)) {
        return $rows;
    }

    $sheetData = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowIndex = (int)$row['r'];
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $colIndex = xlsx_col_to_index($m[1] ?? '');
            $type = (string)$c['t'];
            if ($type === 's') {
                $idx = (int)$c->v;
                $value = $shared[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string)$c->is->t : '';
            } else {
                $value = isset($c->v) ? (string)$c->v : '';
            }
            $cells[$colIndex] = $value;
        }
        if ($cells) {
            $sheetData[$rowIndex] = $cells;
        }
    }

    if (!$sheetData) {
        return $rows;
    }

    ksort($sheetData);
    $rowIndexes = array_keys($sheetData);
    $headerRowIdx = array_shift($rowIndexes);
    $headerCells = $sheetData[$headerRowIdx];
    ksort($headerCells);
    $maxCol = max(array_keys($headerCells));
    $header = [];
    for ($i = 0; $i <= $maxCol; $i++) {
        $header[$i] = strtolower(trim((string)($headerCells[$i] ?? '')));
    }

    foreach ($rowIndexes as $ri) {
        $cells = $sheetData[$ri];
        if (count(array_filter($cells, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $col) {
            if ($col === '') continue;
            $row[$col] = trim((string)($cells[$i] ?? ''));
        }
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Read a CSV or XLSX upload into rows of assoc arrays keyed by normalised
 * header names, dispatching on file extension.
 */
function import_read_file(string $tmpPath, string $ext): array
{
    $ext = strtolower($ext);
    if ($ext === 'csv' || $ext === 'txt') {
        return csv_read($tmpPath);
    }
    if ($ext === 'xlsx') {
        return xlsx_read($tmpPath);
    }
    throw new RuntimeException("Unsupported file type: .$ext");
}

/** Parse a date string in several common formats; returns Y-m-d or null. */
function import_parse_date(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd/m/y', 'Y/m/d', 'j/n/Y', 'n/j/Y'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $s);
        if ($d && $d->format($fmt) === $s) {
            return $d->format('Y-m-d');
        }
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** Parse a time string (HH:MM[:SS]); returns H:i:s or null. */
function import_parse_time(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    foreach (['H:i:s', 'H:i', 'G:i'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $s);
        if ($d) return $d->format('H:i:s');
    }
    return null;
}

/** Resolve a user (checked by) by name. */
function import_resolve_checker(PDO $pdo, ?string $name): ?int
{
    $name = trim((string)$name);
    if ($name === '') return null;
    $stmt = $pdo->prepare('SELECT id FROM m_user WHERE LOWER(name) = LOWER(?) AND is_active = 1 LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/** Empty string to null. */
function import_nz($v)
{
    return ($v === '' || $v === null) ? null : $v;
}

/**
 * Read a value from a CSV row by trying several header aliases (case/space
 * tolerant). Returns the first non-empty match, or '' if none present.
 */
function import_val(array $row, array $aliases): string
{
    foreach ($aliases as $a) {
        $k = strtolower(trim($a));
        if (array_key_exists($k, $row) && trim((string)$row[$k]) !== '') {
            return trim((string)$row[$k]);
        }
    }
    return '';
}
