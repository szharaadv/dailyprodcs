<?php
/**
 * Per-section Excel-export body builders. Each returns
 *   ['cols' => <column count>, 'blocks' => <table-row HTML>]
 * and shares the branded header/footer rendered by export_checksheet.php.
 *
 * Two shapes:
 *  - Record blocks (daily submit sheets): a dark banner (who/when) + a green
 *    column header + item rows + a summary line, per submitted record.
 *  - Day/period matrix (monthly grids): items down the side, days/weeks across.
 */

function _x_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function _x_isng($v): bool { return (bool)preg_match('/\b(ng|abnormal|bocor|reject|not\s*ok|fail|nok)\b/i', (string)$v); }

/** A dark banner row spanning all columns. */
function _x_banner(int $cols, string $text): string
{
    return '<tr><td colspan="' . $cols . '" class="banner">' . $text . '</td></tr>';
}

/** A green column-header row from an array of labels. */
function _x_head(array $labels): string
{
    $o = '<tr>';
    foreach ($labels as $l) $o .= '<td class="hdr">' . _x_e($l) . '</td>';
    return $o . '</tr>';
}

/** A summary row spanning all columns. */
function _x_summary(int $cols, string $text): string
{
    return '<tr><td colspan="' . $cols . '" class="summary">' . $text . '</td></tr>';
}

/** A blank spacer row. */
function _x_spacer(int $cols): string
{
    return '<tr><td colspan="' . $cols . '" style="height:6px;"></td></tr>';
}

/** Dispatch to the right builder. Returns ['cols'=>int, 'blocks'=>string]. */
function export_section_blocks(PDO $pdo, array $section, int $month, int $year): array
{
    $fn = 'export_build_' . str_replace('.php', '', str_replace('_list', '', $section['route']));
    // route → function map (explicit to avoid surprises).
    $map = [
        'painting_list.php'        => 'export_build_painting',
        'assembly_list.php'        => 'export_build_assy',
        'fopump_list.php'          => 'export_build_fopump',
        'fopump_test_list.php'     => 'export_build_fopump_test',
        'fopump_reject_list.php'   => 'export_build_fopump_reject',
        'fopump_check_list.php'    => 'export_build_fopump_check',
        'washing_list.php'         => 'export_build_washing',
        'sub_assembly_list.php'    => 'export_build_jig',
        'bakeoven_list.php'        => 'export_build_bakeoven',
        'paint_viscosity_list.php' => 'export_build_paint_viscosity',
        '3s3t_list.php'            => 'export_build_3s3t',
    ];
    $fn = $map[$section['route']] ?? null;
    if ($fn && function_exists($fn)) return $fn($pdo, $section, $month, $year);
    return ['cols' => 6, 'blocks' => '<tr><td colspan="6">Layout data untuk section ini belum disiapkan.</td></tr>'];
}

// ---------------------------------------------------------------------------
// Daily record-block sheets
// ---------------------------------------------------------------------------

function export_build_painting(PDO $pdo, array $section, int $month, int $year): array
{
    $cols = 7;
    $hq = $pdo->prepare(
        "SELECT h.id, h.tanggal, h.created_at, c.name AS scope, ck.name AS checker
         FROM t_checksheet_header h JOIN m_condition c ON c.id = h.condition_id
         LEFT JOIN m_user ck ON ck.id = h.checker_id
         WHERE h.department_id=? AND h.status='submitted' AND MONTH(h.tanggal)=? AND YEAR(h.tanggal)=?
         ORDER BY h.tanggal, h.id"
    );
    $hq->execute([$section['department_id'], $month, $year]);
    $dq = $pdo->prepare(
        "SELECT i.checking_item, d.actual_result, d.category FROM t_checksheet_detail d
         JOIN m_checklist_item i ON i.id=d.checklist_item_id WHERE d.header_id=? ORDER BY i.sort_order, i.id"
    );
    $blocks = '';
    $headers = $hq->fetchAll();
    foreach ($headers as $h) {
        $dq->execute([$h['id']]);
        $items = $dq->fetchAll();
        $ok = 0; $ng = 0; $n = 0; $body = '';
        foreach ($items as $it) {
            $n++;
            $isng = _x_isng($it['category']) || _x_isng($it['actual_result']);
            $isng ? $ng++ : $ok++;
            $body .= '<tr><td class="b center">' . $n . '</td><td class="b">' . _x_e($h['scope'])
                . '</td><td class="b">' . _x_e($it['checking_item']) . '</td><td class="b center">Checked</td>'
                . '<td class="b center ' . ($isng ? 'ng' : 'ok') . '">' . _x_e($it['category'] ?: ($isng ? 'NG' : 'OK'))
                . '</td><td class="b">' . _x_e(($it['actual_result'] ?? '') !== '' ? $it['actual_result'] : '-')
                . '</td><td class="b center">-</td></tr>';
        }
        $blocks .= _x_banner($cols, 'Checker: ' . _x_e($h['checker'] ?? '-') . ' &nbsp;|&nbsp; Condition: ' . _x_e($h['scope'])
                . ' &nbsp;|&nbsp; Tanggal Cek: ' . _x_e($h['tanggal']) . ' &nbsp;|&nbsp; Submitted: ' . _x_e($h['created_at']))
            . _x_head(['No', 'Unit', 'Part yang Dicek', 'Action', 'Result', 'Keterangan', 'Diedit'])
            . ($items ? $body : '<tr><td colspan="' . $cols . '" class="b center">Tidak ada item.</td></tr>')
            . _x_summary($cols, 'Summary: ' . $n . ' item &mdash; Checked: ' . $n . '&nbsp; OK: ' . $ok . '&nbsp; NG: ' . $ng)
            . _x_spacer($cols);
    }
    if (!$headers) $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada data untuk bulan ini.</td></tr>';
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_assy(PDO $pdo, array $section, int $month, int $year): array
{
    $cols = 7;
    $hq = $pdo->prepare(
        "SELECT h.id, h.tanggal, h.created_at, h.no_engine, m.name AS model_name, ck.name AS checker
         FROM t_assy_header h JOIN m_assy_model m ON m.id=h.model_id
         LEFT JOIN m_user ck ON ck.id=h.checker_id
         WHERE h.department_id=? AND h.status='submitted' AND MONTH(h.tanggal)=? AND YEAR(h.tanggal)=?
         ORDER BY h.tanggal, h.id"
    );
    $hq->execute([$section['department_id'], $month, $year]);
    $dq = $pdo->prepare(
        "SELECT i.checking_item, i.standard, i.standard_min, i.standard_max, d.actual_result, d.consumable_item
         FROM t_assy_detail d JOIN m_assy_checklist_item i ON i.id=d.checklist_item_id WHERE d.header_id=? ORDER BY i.sort_order, i.id"
    );
    $blocks = '';
    $headers = $hq->fetchAll();
    foreach ($headers as $h) {
        $dq->execute([$h['id']]);
        $items = $dq->fetchAll();
        $ok = 0; $ng = 0; $n = 0; $body = '';
        foreach ($items as $it) {
            $n++;
            $act = is_numeric(str_replace(',', '.', (string)$it['actual_result'])) ? (float)str_replace(',', '.', (string)$it['actual_result']) : null;
            $mn = is_numeric((string)$it['standard_min']) ? (float)$it['standard_min'] : null;
            $mx = is_numeric((string)$it['standard_max']) ? (float)$it['standard_max'] : null;
            $isng = ($act !== null && (($mn !== null && $act < $mn) || ($mx !== null && $act > $mx))) || _x_isng($it['actual_result']);
            $isng ? $ng++ : $ok++;
            $std = $it['standard'] ?: trim(($it['standard_min'] ?? '') . ' ~ ' . ($it['standard_max'] ?? ''), ' ~');
            $body .= '<tr><td class="b center">' . $n . '</td><td class="b">' . _x_e($h['model_name'])
                . '</td><td class="b">' . _x_e($it['checking_item']) . '</td><td class="b center">' . _x_e($std ?: '-')
                . '</td><td class="b center ' . ($isng ? 'ng' : 'ok') . '">' . _x_e(($it['actual_result'] ?? '') !== '' ? $it['actual_result'] : '-')
                . '</td><td class="b center">' . _x_e($it['consumable_item'] ?: '-') . '</td><td class="b center">-</td></tr>';
        }
        $eng = $h['no_engine'] ? ' · Eng ' . $h['no_engine'] : '';
        $blocks .= _x_banner($cols, 'Checker: ' . _x_e($h['checker'] ?? '-') . ' &nbsp;|&nbsp; Model: ' . _x_e($h['model_name'] . $eng)
                . ' &nbsp;|&nbsp; Tanggal Cek: ' . _x_e($h['tanggal']) . ' &nbsp;|&nbsp; Submitted: ' . _x_e($h['created_at']))
            . _x_head(['No', 'Model', 'Checking Item', 'Standard', 'Actual', 'Consumable', 'Diedit'])
            . ($items ? $body : '<tr><td colspan="' . $cols . '" class="b center">Tidak ada item.</td></tr>')
            . _x_summary($cols, 'Summary: ' . $n . ' item &mdash; Checked: ' . $n . '&nbsp; OK: ' . $ok . '&nbsp; NG: ' . $ng)
            . _x_spacer($cols);
    }
    if (!$headers) $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada data untuk bulan ini.</td></tr>';
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_fopump(PDO $pdo, array $section, int $month, int $year): array
{
    // Daily Production Report — production/assembly/export quantities per line.
    $cols = 7;
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
    $blocks = '';
    $headers = $hq->fetchAll();
    foreach ($headers as $h) {
        $lq->execute([$h['id']]);
        $lines = $lq->fetchAll();
        $tp = 0; $ta = 0; $tx = 0; $body = '';
        foreach ($lines as $l) {
            $tp += (int)$l['production_qty']; $ta += (int)$l['assembly_qty']; $tx += (int)$l['export_qty'];
            $body .= '<tr><td class="b center">' . (int)$l['line_no'] . '</td>'
                . '<td class="b">' . _x_e($l['production_model']) . '</td><td class="b center">' . _x_e($l['production_qty']) . '</td>'
                . '<td class="b">' . _x_e($l['assembly_model']) . '</td><td class="b center">' . _x_e($l['assembly_qty']) . '</td>'
                . '<td class="b">' . _x_e($l['export_model']) . '</td><td class="b center">' . _x_e($l['export_qty']) . '</td></tr>';
        }
        $blocks .= _x_banner($cols, 'Operator: ' . _x_e($h['operator'] ?? '-') . ' &nbsp;|&nbsp; Tanggal: ' . _x_e($h['tanggal'])
                . ' &nbsp;|&nbsp; Submitted: ' . _x_e($h['created_at']))
            . _x_head(['No', 'Production Model', 'Qty', 'Assembly Model', 'Qty', 'Export Model', 'Qty'])
            . ($lines ? $body : '<tr><td colspan="' . $cols . '" class="b center">Tidak ada baris.</td></tr>')
            . _x_summary($cols, 'Total &mdash; Production: ' . $tp . '&nbsp; Assembly: ' . $ta . '&nbsp; Export: ' . $tx)
            . _x_spacer($cols);
    }
    if (!$headers) $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada data untuk bulan ini.</td></tr>';
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_fopump_test(PDO $pdo, array $section, int $month, int $year): array
{
    // Ongoing per model — export the current records (no month filter).
    $cols = 5;
    $hq = $pdo->prepare(
        "SELECT h.id, h.created_at, h.destination, m.name AS model_name, ck.name AS checker
         FROM t_fopump_test_header h JOIN m_fopump_test_model m ON m.id=h.model_id
         LEFT JOIN m_user ck ON ck.id=h.checker_id WHERE h.department_id=? AND h.status='submitted'
         ORDER BY m.id"
    );
    $hq->execute([$section['department_id']]);
    $rq = $pdo->prepare("SELECT row_no, rpm, cc_sec, shim FROM t_fopump_test_row WHERE header_id=? ORDER BY sort_order, row_no");
    $blocks = '';
    $headers = $hq->fetchAll();
    foreach ($headers as $h) {
        $rq->execute([$h['id']]);
        $rows = $rq->fetchAll();
        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr><td class="b center">' . (int)$r['row_no'] . '</td><td class="b">' . _x_e($h['model_name'])
                . '</td><td class="b center">' . _x_e($r['rpm']) . '</td><td class="b center">' . _x_e($r['cc_sec'])
                . '</td><td class="b center">' . _x_e($r['shim']) . '</td></tr>';
        }
        $blocks .= _x_banner($cols, 'Checker: ' . _x_e($h['checker'] ?? '-') . ' &nbsp;|&nbsp; Model: ' . _x_e($h['model_name'])
                . ' &nbsp;|&nbsp; Destination: ' . _x_e($h['destination']) . ' &nbsp;|&nbsp; Submitted: ' . _x_e($h['created_at']))
            . _x_head(['No', 'Model', 'RPM', 'CC/sec', 'Shim'])
            . ($rows ? $body : '<tr><td colspan="' . $cols . '" class="b center">Tidak ada baris.</td></tr>')
            . _x_spacer($cols);
    }
    if (!$headers) $blocks = '<tr><td colspan="' . $cols . '" class="b center">Belum ada data.</td></tr>';
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_fopump_reject(PDO $pdo, array $section, int $month, int $year): array
{
    // Monthly — reject lines for the month.
    $cols = 4;
    $hq = $pdo->prepare(
        "SELECT id, target FROM t_fopump_reject_header WHERE department_id=? AND month=? AND year=? LIMIT 1"
    );
    $hq->execute([$section['department_id'], $month, $year]);
    $h = $hq->fetch();
    $blocks = '';
    if ($h) {
        $lq = $pdo->prepare("SELECT line_no, model, quantity, remarks FROM t_fopump_reject_line WHERE header_id=? ORDER BY line_no");
        $lq->execute([$h['id']]);
        $lines = $lq->fetchAll();
        $tot = 0; $body = '';
        foreach ($lines as $l) {
            $tot += (int)$l['quantity'];
            $body .= '<tr><td class="b center">' . (int)$l['line_no'] . '</td><td class="b">' . _x_e($l['model'])
                . '</td><td class="b center">' . _x_e($l['quantity']) . '</td><td class="b">' . _x_e($l['remarks']) . '</td></tr>';
        }
        $blocks = _x_banner($cols, 'Reject Bulan Ini' . ($h['target'] !== null ? ' &nbsp;|&nbsp; Target: ' . _x_e($h['target']) : ''))
            . _x_head(['No', 'Model', 'Quantity', 'Remarks'])
            . ($lines ? $body : '<tr><td colspan="' . $cols . '" class="b center">Tidak ada data reject.</td></tr>')
            . _x_summary($cols, 'Total Reject: ' . $tot);
    } else {
        $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada data untuk bulan ini.</td></tr>';
    }
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_washing(PDO $pdo, array $section, int $month, int $year): array
{
    // Monthly — one row per day with the day's readings.
    $cols = 6;
    $hq = $pdo->prepare("SELECT id FROM t_washing_header WHERE department_id=? AND month=? AND year=? LIMIT 1");
    $hq->execute([$section['department_id'], $month, $year]);
    $h = $hq->fetch();
    $blocks = '';
    if ($h) {
        $dq = $pdo->prepare("SELECT day, ganti_air, temperatur_air, penambahan_gildaon, total_acid FROM t_washing_detail WHERE header_id=? ORDER BY day");
        $dq->execute([$h['id']]);
        $rows = $dq->fetchAll();
        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr><td class="b center">' . (int)$r['day'] . '</td><td class="b center">' . _x_e($r['ganti_air'])
                . '</td><td class="b center">' . _x_e($r['temperatur_air']) . '</td><td class="b center">' . _x_e($r['penambahan_gildaon'])
                . '</td><td class="b center">' . _x_e($r['total_acid']) . '</td><td class="b center">-</td></tr>';
        }
        $blocks = _x_banner($cols, 'Washing Machine — Bulan Ini')
            . _x_head(['Tgl', 'Ganti Air', 'Temperatur Air', 'Penambahan Gildaon', 'Total Acid', 'Diedit'])
            . ($rows ? $body : '<tr><td colspan="' . $cols . '" class="b center">Belum ada isian bulan ini.</td></tr>');
    } else {
        $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada data untuk bulan ini.</td></tr>';
    }
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_3s3t(PDO $pdo, array $section, int $month, int $year): array
{
    // Monthly — item rows with 5 weekly columns + remarks.
    $cols = 9;
    $hq = $pdo->prepare("SELECT id, line FROM t_3s3t_header WHERE department_id=? AND month=? AND year=? ORDER BY line");
    $hq->execute([$section['department_id'], $month, $year]);
    $headers = $hq->fetchAll();
    $dq = $pdo->prepare(
        "SELECT i.category, i.item_pemeriksaan, d.week1, d.week2, d.week3, d.week4, d.week5, d.remarks
         FROM t_3s3t_detail d JOIN m_3s3t_item i ON i.id=d.item_id WHERE d.header_id=? ORDER BY i.sort_order, i.id"
    );
    $blocks = '';
    foreach ($headers as $h) {
        $dq->execute([$h['id']]);
        $rows = $dq->fetchAll();
        $n = 0; $body = '';
        foreach ($rows as $r) {
            $n++;
            $body .= '<tr><td class="b center">' . $n . '</td><td class="b">' . _x_e($r['category']) . '</td><td class="b">' . _x_e($r['item_pemeriksaan']) . '</td>';
            foreach (['week1', 'week2', 'week3', 'week4', 'week5'] as $w) {
                $v = $r[$w];
                $cls = _x_isng($v) ? 'ng' : ($v !== null && $v !== '' ? 'ok' : '');
                $body .= '<td class="b center ' . $cls . '">' . _x_e(($v ?? '') !== '' ? $v : '-') . '</td>';
            }
            $body .= '<td class="b">' . _x_e($r['remarks']) . '</td></tr>';
        }
        $blocks .= _x_banner($cols, 'Checksheet 3S-3T &nbsp;|&nbsp; Line: ' . _x_e($h['line']))
            . _x_head(['No', 'Category', 'Item Pemeriksaan', 'W1', 'W2', 'W3', 'W4', 'W5', 'Remarks'])
            . ($rows ? $body : '<tr><td colspan="' . $cols . '" class="b center">Belum ada isian.</td></tr>')
            . _x_spacer($cols);
    }
    if (!$headers) $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada data untuk bulan ini.</td></tr>';
    return ['cols' => $cols, 'blocks' => $blocks];
}

// ---------------------------------------------------------------------------
// Day-matrix monthly grids (items down, days across)
// ---------------------------------------------------------------------------

/** Shared item × day matrix. $itemRows: [['label'=>, 'id'=>], …]; $cell($itemId,$day)→string. */
function _x_day_matrix(int $daysInMonth, string $bannerText, string $itemColLabel, array $itemRows, callable $cell): array
{
    $cols = 2 + $daysInMonth;
    $labels = ['No', $itemColLabel];
    for ($d = 1; $d <= $daysInMonth; $d++) $labels[] = (string)$d;
    $blocks = _x_banner($cols, $bannerText) . _x_head($labels);
    if (!$itemRows) {
        $blocks .= '<tr><td colspan="' . $cols . '" class="b center">Belum ada item.</td></tr>';
        return ['cols' => $cols, 'blocks' => $blocks];
    }
    $n = 0;
    foreach ($itemRows as $it) {
        $n++;
        $row = '<tr><td class="b center">' . $n . '</td><td class="b">' . _x_e($it['label']) . '</td>';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $v = (string)$cell($it['id'], $d);
            $cls = _x_isng($v) ? 'ng' : ($v !== '' ? 'ok' : '');
            $row .= '<td class="b center ' . $cls . '" style="font-size:9pt;">' . _x_e($v) . '</td>';
        }
        $blocks .= $row . '</tr>';
    }
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_jig(PDO $pdo, array $section, int $month, int $year): array
{
    $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    // One header per jig for the month; combine all jigs of the department.
    $jigs = $pdo->prepare("SELECT id, name FROM m_jig WHERE department_id=? AND is_active=1 ORDER BY sort_order, id");
    $jigs->execute([$section['department_id']]);
    $blocks = ''; $cols = 2 + $days;
    foreach ($jigs->fetchAll() as $jig) {
        $hq = $pdo->prepare("SELECT id FROM t_jigheader WHERE jig_id=? AND month=? AND year=? LIMIT 1");
        $hq->execute([$jig['id'], $month, $year]);
        $hid = $hq->fetchColumn();
        $items = $pdo->prepare("SELECT id, checking_item FROM m_jigitem WHERE jig_id=? AND is_active=1 ORDER BY sort_order, id");
        $items->execute([$jig['id']]);
        $itemRows = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['checking_item']], $items->fetchAll());
        $vals = [];
        if ($hid) {
            $dq = $pdo->prepare("SELECT jig_item_id, day, result FROM t_jig_detail WHERE header_id=?");
            $dq->execute([$hid]);
            foreach ($dq->fetchAll() as $d) $vals[$d['jig_item_id'] . '-' . (int)$d['day']] = $d['result'];
        }
        $res = _x_day_matrix($days, 'Sub Assembly (Jig): ' . _x_e($jig['name']), 'Checking Item', $itemRows,
            fn($id, $day) => $vals[$id . '-' . $day] ?? '');
        $blocks .= $res['blocks'] . _x_spacer($res['cols']);
        $cols = $res['cols'];
    }
    if ($blocks === '') $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada jig.</td></tr>';
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_bakeoven(PDO $pdo, array $section, int $month, int $year): array
{
    $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $ovens = $pdo->prepare("SELECT id, name FROM m_bakeoven WHERE department_id=? AND is_active=1 ORDER BY sort_order, id");
    $ovens->execute([$section['department_id']]);
    $blocks = ''; $cols = 2 + $days;
    foreach ($ovens->fetchAll() as $ov) {
        $hq = $pdo->prepare("SELECT id FROM t_bakeoven_header WHERE bakeoven_id=? AND month=? AND year=? LIMIT 1");
        $hq->execute([$ov['id'], $month, $year]);
        $hid = $hq->fetchColumn();
        $times = $pdo->prepare("SELECT id, time_label FROM m_bakeoven_time WHERE bakeoven_id=? AND is_active=1 ORDER BY sort_order, id");
        $times->execute([$ov['id']]);
        $itemRows = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['time_label']], $times->fetchAll());
        $vals = [];
        if ($hid) {
            $dq = $pdo->prepare("SELECT time_id, day, actual_temp FROM t_bakeoven_detail WHERE header_id=?");
            $dq->execute([$hid]);
            foreach ($dq->fetchAll() as $d) $vals[$d['time_id'] . '-' . (int)$d['day']] = $d['actual_temp'];
        }
        $res = _x_day_matrix($days, 'Bake Oven: ' . _x_e($ov['name']), 'Waktu Cek', $itemRows,
            fn($id, $day) => $vals[$id . '-' . $day] ?? '');
        $blocks .= $res['blocks'] . _x_spacer($res['cols']);
        $cols = $res['cols'];
    }
    if ($blocks === '') $blocks = '<tr><td colspan="' . $cols . '" class="b center">Tidak ada oven.</td></tr>';
    return ['cols' => $cols, 'blocks' => $blocks];
}

function export_build_paint_viscosity(PDO $pdo, array $section, int $month, int $year): array
{
    $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $hq = $pdo->prepare("SELECT id FROM t_paint_viscosity_header WHERE department_id=? AND month=? AND year=? LIMIT 1");
    $hq->execute([$section['department_id'], $month, $year]);
    $hid = $hq->fetchColumn();
    $items = $pdo->prepare("SELECT id, process_name, product_name FROM m_paint_viscosity_item WHERE department_id=? AND is_active=1 ORDER BY sort_order, id");
    $items->execute([$section['department_id']]);
    $itemRows = array_map(fn($r) => ['id' => $r['id'], 'label' => trim($r['process_name'] . ' / ' . $r['product_name'], ' /')], $items->fetchAll());
    $vals = [];
    if ($hid) {
        $dq = $pdo->prepare("SELECT item_id, day, actual_result FROM t_paint_viscosity_detail WHERE header_id=?");
        $dq->execute([$hid]);
        foreach ($dq->fetchAll() as $d) $vals[$d['item_id'] . '-' . (int)$d['day']] = $d['actual_result'];
    }
    return _x_day_matrix($days, 'Paint Viscosity — Bulan Ini', 'Process / Product', $itemRows,
        fn($id, $day) => $vals[$id . '-' . $day] ?? '');
}

function export_build_fopump_check(PDO $pdo, array $section, int $month, int $year): array
{
    // Daily per model — item rows with one column per sample.
    $hq = $pdo->prepare(
        "SELECT h.id, h.created_at, m.name AS model_name, ck.name AS checker
         FROM t_fopump_check_header h JOIN m_fopump_check_model m ON m.id=h.model_id
         LEFT JOIN m_user ck ON ck.id=h.checker_id
         WHERE h.department_id=? AND h.status='submitted' AND MONTH(h.tanggal)=? AND YEAR(h.tanggal)=?
         ORDER BY m.sort_order, m.id"
    );
    $hq->execute([$section['department_id'], $month, $year]);
    $headers = $hq->fetchAll();
    $items = $pdo->query("SELECT id, checking_item, standard FROM m_fopump_check_item WHERE model_id IS NULL AND is_active=1 ORDER BY sort_order, id")->fetchAll();
    $blocks = ''; $maxCols = 3;
    foreach ($headers as $h) {
        $sq = $pdo->prepare("SELECT id, sample_no FROM t_fopump_check_sample WHERE header_id=? ORDER BY sort_order, id");
        $sq->execute([$h['id']]);
        $samples = $sq->fetchAll();
        $vq = $pdo->prepare("SELECT checklist_item_id, sample_id, actual_result FROM t_fopump_check_detail WHERE header_id=?");
        $vq->execute([$h['id']]);
        $vals = [];
        foreach ($vq->fetchAll() as $d) $vals[$d['checklist_item_id'] . '-' . $d['sample_id']] = $d['actual_result'];

        $cols = 3 + count($samples);
        $maxCols = max($maxCols, $cols);
        $labels = ['No', 'Checking Item', 'Standard'];
        foreach ($samples as $s) $labels[] = 'No.' . $s['sample_no'];
        $n = 0; $body = '';
        foreach ($items as $it) {
            $n++;
            $body .= '<tr><td class="b center">' . $n . '</td><td class="b">' . _x_e($it['checking_item']) . '</td><td class="b center">' . _x_e($it['standard'] ?: '-') . '</td>';
            foreach ($samples as $s) {
                $v = $vals[$it['id'] . '-' . $s['id']] ?? '';
                $cls = _x_isng($v) || $v === 'FALSE' ? 'ng' : ($v !== '' ? 'ok' : '');
                $body .= '<td class="b center ' . $cls . '">' . _x_e($v !== '' ? $v : '-') . '</td>';
            }
            $body .= '</tr>';
        }
        $blocks .= _x_banner($cols, 'FO Pump Check &nbsp;|&nbsp; Model: ' . _x_e($h['model_name'])
                . ' &nbsp;|&nbsp; Checker: ' . _x_e($h['checker'] ?? '-') . ' &nbsp;|&nbsp; ' . _x_e($h['created_at']))
            . _x_head($labels)
            . ($items ? $body : '<tr><td colspan="' . $cols . '" class="b center">Tidak ada item.</td></tr>')
            . _x_spacer($cols);
    }
    if (!$headers) $blocks = '<tr><td colspan="' . $maxCols . '" class="b center">Tidak ada data untuk bulan ini.</td></tr>';
    return ['cols' => $maxCols, 'blocks' => $blocks];
}
