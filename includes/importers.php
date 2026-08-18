<?php
require_once __DIR__ . '/import_lib.php';

/**
 * Registry of importable sections, keyed by section route.
 * Each entry: label, template (CSV column order), note, and importer fn name.
 * Every importer fn signature: fn(PDO $pdo, int $department_id, array $rows): array
 *   returns ['created'=>int, 'updated'=>int, 'skipped'=>int, 'errors'=>string[]]
 */
function import_registry(): array
{
    return [
        'assembly_list.php' => [
            'label'    => 'Torque (Daily Torque)',
            'template' => ['Checking Item', 'DetailModel', 'Date', 'Standard', 'StandardMin.', 'StandardMax.',
                           'ActualResult', 'ConsumableItem', 'NoCylBlock', 'NoEngine', 'Model',
                           'MarkConrod', 'MarkCrankShaft', 'MarkFOPump', 'Checker'],
            'note'     => 'One row per checking item. Rows with the same Date + Model form one sheet; header fields repeat. Checking Item / Model must match master data. Standard/StandardMin./StandardMax. come from master data and are ignored on import.',
            'fn'       => 'import_torque',
            'export'   => 'export_torque',
            'template_include_readonly' => true,
            'groups'   => [
                ['label' => 'Header — repeat on every row of the same Date + Model',
                 'cols'  => ['DetailModel', 'Date', 'NoCylBlock', 'NoEngine', 'Model',
                             'MarkConrod', 'MarkCrankShaft', 'MarkFOPump', 'Checker']],
                ['label' => 'Checklist detail — one row per checking item',
                 'cols'  => ['Checking Item', 'ActualResult', 'ConsumableItem']],
                ['label' => 'Reference from master data — included in the template layout, ignored on import',
                 'cols'  => ['Standard', 'StandardMin.', 'StandardMax.'],
                 'readonly' => true],
            ],
        ],
        'painting_list.php' => [
            'label'    => 'Painting Checklist',
            'template' => ['Condition', 'Date', 'Checking Item', 'Metode Pengecekkan', 'Standard Min.', 'Standard Max.',
                           'Satuan', 'Shift', 'Jam', 'Tank/Tube', 'Actual Result', 'Category', 'Checked By :'],
            'note'     => 'One row per checking item. Rows with the same Date + Condition + Jam form one sheet; header fields repeat. Condition / Checking Item must match master data. Descriptive columns (Metode, Standard, Satuan, Tank/Tube) come from master data and are ignored on import.',
            'fn'       => 'import_painting',
            'export'   => 'export_painting',
            'template_include_readonly' => true,
            'groups'   => [
                ['label' => 'Header — repeat on every row of the same Date + Condition + Jam',
                 'cols'  => ['Condition', 'Date', 'Shift', 'Jam', 'Checked By :']],
                ['label' => 'Checklist detail — one row per checking item',
                 'cols'  => ['Checking Item', 'Actual Result', 'Category']],
                ['label' => 'Reference from master data — included in the template layout, ignored on import',
                 'cols'  => ['Metode Pengecekkan', 'Standard Min.', 'Standard Max.', 'Satuan', 'Tank/Tube'],
                 'readonly' => true],
            ],
        ],
    ];
}

/** Columns marked readonly in a section's groups (reference — excluded from the fillable template). */
function import_readonly_cols(array $cfg): array
{
    $cols = [];
    foreach ($cfg['groups'] ?? [] as $g) {
        if (!empty($g['readonly'])) $cols = array_merge($cols, $g['cols']);
    }
    return $cols;
}

/** The columns for the downloadable blank template. */
function import_fillable_cols(array $cfg): array
{
    if (!empty($cfg['template_include_readonly'])) {
        return $cfg['template'];
    }
    $ro = import_readonly_cols($cfg);
    return array_values(array_filter($cfg['template'], fn($c) => !in_array($c, $ro, true)));
}

/** Group rows by a composite key built from the given columns. Preserves order. */
function import_group(array $rows, array $keyCols): array
{
    $groups = [];
    foreach ($rows as $i => $r) {
        $key = implode('||', array_map(fn($c) => strtolower(trim((string)($r[$c] ?? ''))), $keyCols));
        $groups[$key]['rows'][] = ['_line' => $i + 2, 'data' => $r];
    }
    return $groups;
}

// ---------------------------------------------------------------------------
// Torque
// ---------------------------------------------------------------------------

/** Field-alias map for Torque. */
function torque_aliases(): array
{
    return [
        'tanggal'          => ['date', 'tanggal'],
        'model'            => ['model'],
        'checker'          => ['checker', 'checked by', 'checked by :'],
        'mark_crank_shaft' => ['markcrankshaft', 'mark crank shaft', 'mark_crank_shaft'],
        'mark_conrod'      => ['markconrod', 'mark conrod', 'mark_conrod'],
        'mark_fo_pump'     => ['markfopump', 'mark fo pump', 'mark_fo_pump'],
        'no_cyl_block'     => ['nocylblock', 'no cyl block', 'no_cyl_block'],
        'no_engine'        => ['noengine', 'no engine', 'no_engine'],
        'detail_model'     => ['detailmodel', 'detail model', 'detail_model'],
        'checking_item'    => ['checking item', 'checking_item'],
        'actual_result'    => ['actualresult', 'actual result', 'actual_result'],
        'consumable_item'  => ['consumableitem', 'consumable item', 'consumable_item', 'cosumbleitem', 'cosumble item'],
    ];
}

function import_torque(PDO $pdo, int $dept, array $rows): array
{
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $A = torque_aliases();
    foreach ($rows as &$r) {
        $r = [
            'tanggal'          => import_val($r, $A['tanggal']),
            'model'            => import_val($r, $A['model']),
            'checker'          => import_val($r, $A['checker']),
            'mark_crank_shaft' => import_val($r, $A['mark_crank_shaft']),
            'mark_conrod'      => import_val($r, $A['mark_conrod']),
            'mark_fo_pump'     => import_val($r, $A['mark_fo_pump']),
            'no_cyl_block'     => import_val($r, $A['no_cyl_block']),
            'no_engine'        => import_val($r, $A['no_engine']),
            'detail_model'     => import_val($r, $A['detail_model']),
            'checking_item'    => import_val($r, $A['checking_item']),
            'actual_result'    => import_val($r, $A['actual_result']),
            'consumable_item'  => import_val($r, $A['consumable_item']),
        ];
    }
    unset($r);
    $groups = import_group($rows, ['tanggal', 'model']);

    $selH = $pdo->prepare('SELECT id FROM t_assy_header WHERE department_id=? AND tanggal=? AND model_id=?');
    $insH = $pdo->prepare('INSERT INTO t_assy_header (tanggal,department_id,model_id,mark_crank_shaft,mark_conrod,mark_fo_pump,no_cyl_block,no_engine,detail_model,checker_id,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,\'submitted\')');
    $updH = $pdo->prepare('UPDATE t_assy_header SET mark_crank_shaft=?,mark_conrod=?,mark_fo_pump=?,no_cyl_block=?,no_engine=?,detail_model=?,checker_id=?,status=\'submitted\' WHERE id=?');
    $delD = $pdo->prepare('DELETE FROM t_assy_detail WHERE header_id=?');
    $insD = $pdo->prepare('INSERT INTO t_assy_detail (header_id,checklist_item_id,actual_result,consumable_item) VALUES (?,?,?,?)');
    $selItem = $pdo->prepare('SELECT id FROM m_assy_checklist_item WHERE model_id=? AND LOWER(checking_item)=LOWER(?) LIMIT 1');
    $selModel = $pdo->prepare('SELECT id FROM m_assy_model WHERE department_id=? AND LOWER(name)=LOWER(?) AND is_active=1 LIMIT 1');

    foreach ($groups as $g) {
        $first = $g['rows'][0]['data'];
        $line = $g['rows'][0]['_line'];
        $tgl = import_parse_date($first['tanggal'] ?? '');
        if (!$tgl) { $res['skipped']++; $res['errors'][] = "Row $line: invalid tanggal."; continue; }
        $selModel->execute([$dept, $first['model'] ?? '']);
        $model_id = (int)$selModel->fetchColumn();
        if (!$model_id) { $res['skipped']++; $res['errors'][] = "Row $line: model '" . ($first['model'] ?? '') . "' not found."; continue; }

        $checker = import_resolve_checker($pdo, $first['checker'] ?? '');
        if (!$checker) {
            $res['skipped']++;
            $res['errors'][] = "Row $line: checker '" . ($first['checker'] ?? '') . "' not found or blank — checker is required, row skipped.";
            continue;
        }

        $params = [import_nz($first['mark_crank_shaft'] ?? ''), import_nz($first['mark_conrod'] ?? ''), import_nz($first['mark_fo_pump'] ?? ''),
            import_nz($first['no_cyl_block'] ?? ''), import_nz($first['no_engine'] ?? ''), import_nz($first['detail_model'] ?? ''), $checker];

        $selH->execute([$dept, $tgl, $model_id]);
        $hid = (int)$selH->fetchColumn();
        if ($hid) {
            $updH->execute(array_merge($params, [$hid]));
            $delD->execute([$hid]);
            $res['updated']++;
        } else {
            $insH->execute([$tgl, $dept, $model_id, $params[0], $params[1], $params[2], $params[3], $params[4], $params[5], $params[6]]);
            $hid = (int)$pdo->lastInsertId();
            $res['created']++;
        }

        foreach ($g['rows'] as $rr) {
            $item = trim((string)($rr['data']['checking_item'] ?? ''));
            if ($item === '') continue;
            $selItem->execute([$model_id, $item]);
            $iid = (int)$selItem->fetchColumn();
            if (!$iid) { $res['errors'][] = "Row {$rr['_line']}: checking_item '$item' not found for model '{$first['model']}' (skipped)."; continue; }
            $insD->execute([$hid, $iid, import_nz($rr['data']['actual_result'] ?? ''), import_nz($rr['data']['consumable_item'] ?? '')]);
        }
    }
    return $res;
}

// ---------------------------------------------------------------------------
// Painting
// ---------------------------------------------------------------------------

/** Field-alias map for Painting. */
function painting_aliases(): array
{
    return [
        'date'          => ['date', 'tanggal'],
        'condition'     => ['condition', 'kondisi'],
        'checking_item' => ['checking item', 'checking_item'],
        'shift'         => ['shift'],
        'jam'           => ['jam', 'time', 'waktu'],
        'actual_result' => ['actual result', 'actual_result', 'actual'],
        'category'      => ['category', 'kategori'],
        'checker'       => ['checked by :', 'checked by', 'checker'],
        'tank_tube'     => ['tank/tube', 'tank_tube'],
    ];
}

function import_painting(PDO $pdo, int $dept, array $rows): array
{
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $A = painting_aliases();
    foreach ($rows as &$r) {
        $r = [
            'tanggal'       => import_val($r, $A['date']),
            'condition'     => import_val($r, $A['condition']),
            'checking_item' => import_val($r, $A['checking_item']),
            'shift'         => import_val($r, $A['shift']),
            'jam'           => import_val($r, $A['jam']),
            'actual_result' => import_val($r, $A['actual_result']),
            'category'      => import_val($r, $A['category']),
            'checker'       => import_val($r, $A['checker']),
            'tank_tube'     => import_val($r, $A['tank_tube']),
        ];
    }
    unset($r);
    $groups = import_group($rows, ['tanggal', 'condition', 'jam']);

    $selH = $pdo->prepare('SELECT id FROM t_checksheet_header WHERE department_id=? AND tanggal=? AND condition_id=? AND jam <=> ?');
    $insH = $pdo->prepare('INSERT INTO t_checksheet_header (tanggal,condition_id,department_id,checker_id,jam,shift_id,status) VALUES (?,?,?,?,?,?,\'submitted\')');
    $updH = $pdo->prepare('UPDATE t_checksheet_header SET checker_id=?,shift_id=?,status=\'submitted\' WHERE id=?');
    $delD = $pdo->prepare('DELETE FROM t_checksheet_detail WHERE header_id=?');
    $insD = $pdo->prepare('INSERT INTO t_checksheet_detail (header_id,checklist_item_id,actual_result,category) VALUES (?,?,?,?)');
    $selCond = $pdo->prepare('SELECT id FROM m_condition WHERE department_id=? AND LOWER(name)=LOWER(?) AND is_active=1 LIMIT 1');
    $selItem = $pdo->prepare('SELECT id FROM m_checklist_item WHERE condition_id=? AND LOWER(checking_item)=LOWER(?) LIMIT 1');
    $selShift = $pdo->prepare('SELECT id FROM m_shift WHERE LOWER(name)=LOWER(?) AND is_active=1 LIMIT 1');

    foreach ($groups as $g) {
        $first = $g['rows'][0]['data'];
        $line = $g['rows'][0]['_line'];
        $tgl = import_parse_date($first['tanggal'] ?? '');
        if (!$tgl) { $res['skipped']++; $res['errors'][] = "Row $line: invalid tanggal."; continue; }
        $selCond->execute([$dept, $first['condition'] ?? '']);
        $cond_id = (int)$selCond->fetchColumn();
        if (!$cond_id) { $res['skipped']++; $res['errors'][] = "Row $line: condition '" . ($first['condition'] ?? '') . "' not found."; continue; }

        $jam = import_parse_time($first['jam'] ?? '');
        $checker = import_resolve_checker($pdo, $first['checker'] ?? '');
        if (!$checker) {
            $res['skipped']++;
            $res['errors'][] = "Row $line: Checked By '" . ($first['checker'] ?? '') . "' not found or blank — Checked By is required, row skipped.";
            continue;
        }
        $shift_id = null;
        if (!empty($first['shift'])) {
            $selShift->execute([$first['shift']]);
            $shift_id = (int)$selShift->fetchColumn() ?: null;
        }
        if (!$shift_id) {
            $res['skipped']++;
            $res['errors'][] = "Row $line: shift '" . ($first['shift'] ?? '') . "' not found or blank — shift is required, row skipped.";
            continue;
        }

        $selH->execute([$dept, $tgl, $cond_id, $jam]);
        $hid = (int)$selH->fetchColumn();
        if ($hid) {
            $updH->execute([$checker, $shift_id, $hid]);
            $delD->execute([$hid]);
            $res['updated']++;
        } else {
            $insH->execute([$tgl, $cond_id, $dept, $checker, $jam, $shift_id]);
            $hid = (int)$pdo->lastInsertId();
            $res['created']++;
        }

        // Per-group occurrence counter — lets rows that repeat the same
        // generic label (e.g. three "Exhaust fan" rows, one per physical
        // unit) resolve in order to the matching numbered master items
        // ("Exhaust fan 1", "2", "3"), instead of all colliding on the first.
        $occurrence = [];
        foreach ($g['rows'] as $rr) {
            $item = trim((string)($rr['data']['checking_item'] ?? ''));
            if ($item === '') continue;
            $selItem->execute([$cond_id, $item]);
            $iid = (int)$selItem->fetchColumn();
            if (!$iid) {
                $tankTube = trim((string) ($rr['data']['tank_tube'] ?? ''));
                if ($tankTube !== '') {
                    $selItem->execute([$cond_id, "$item (Line $tankTube)"]);
                    $iid = (int) $selItem->fetchColumn();
                }
            }
            if (!$iid) {
                $iid = import_match_checklist_item_loose($pdo, $cond_id, $item, $occurrence);
            }
            if (!$iid) { $res['errors'][] = "Row {$rr['_line']}: checking_item '$item' not found for condition '{$first['condition']}' (skipped)."; continue; }
            $insD->execute([$hid, $iid, import_nz($rr['data']['actual_result'] ?? ''), import_nz($rr['data']['category'] ?? '')]);
        }
    }
    return $res;
}

/**
 * Loose fallback once exact/tank_tube matching fails: normalize away
 * punctuation/spacing ("Visual phosphat spray (tank 1)" -> "Filter condition
 * tank1"-style names both collapse to the same key) and, for CSV labels that
 * are a prefix of several numbered master items sharing one base name
 * (e.g. "Exhaust fan" vs "Exhaust fan 1/2/3"), resolve each repeated
 * occurrence in a group to the next one in sort_order.
 */
function import_match_checklist_item_loose(PDO $pdo, int $cond_id, string $item, array &$occurrence): int
{
    static $itemsByCondition = [];
    if (!isset($itemsByCondition[$cond_id])) {
        $stmt = $pdo->prepare('SELECT id, checking_item FROM m_checklist_item WHERE condition_id = ? ORDER BY sort_order, id');
        $stmt->execute([$cond_id]);
        $itemsByCondition[$cond_id] = $stmt->fetchAll();
    }
    $allItems = $itemsByCondition[$cond_id];
    $normItem = import_normalize_name($item);
    if ($normItem === '') return 0;

    $exact = array_values(array_filter($allItems, fn($ci) => import_normalize_name($ci['checking_item']) === $normItem));
    if (count($exact) === 1) return (int)$exact[0]['id'];

    $prefixed = array_values(array_filter($allItems, fn($ci) => str_starts_with(import_normalize_name($ci['checking_item']), $normItem)));
    if ($prefixed) {
        $occurrence[$normItem] = ($occurrence[$normItem] ?? 0) + 1;
        $idx = $occurrence[$normItem] - 1;
        if (isset($prefixed[$idx])) return (int)$prefixed[$idx]['id'];
    }

    return 0;
}

// ===========================================================================
// EXPORT
// ===========================================================================

function export_torque(PDO $pdo, int $dept): array
{
    $stmt = $pdo->prepare(
        'SELECT h.*, m.name AS model_name, ck.name AS checker_name,
                d.actual_result, d.consumable_item, ci.checking_item, ci.standard, ci.standard_min, ci.standard_max, ci.sort_order AS so
         FROM t_assy_header h
         JOIN t_assy_detail d ON d.header_id = h.id
         JOIN m_assy_checklist_item ci ON ci.id = d.checklist_item_id
         JOIN m_assy_model m ON m.id = h.model_id
         LEFT JOIN m_user ck ON ck.id = h.checker_id
         WHERE h.department_id = ? ORDER BY h.tanggal, h.id, ci.sort_order'
    );
    $stmt->execute([$dept]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'Checking Item' => $r['checking_item'], 'DetailModel' => $r['detail_model'], 'Date' => $r['tanggal'],
            'Standard' => $r['standard'], 'StandardMin.' => $r['standard_min'], 'StandardMax.' => $r['standard_max'],
            'ActualResult' => $r['actual_result'], 'ConsumableItem' => $r['consumable_item'],
            'NoCylBlock' => $r['no_cyl_block'], 'NoEngine' => $r['no_engine'], 'Model' => $r['model_name'],
            'MarkConrod' => $r['mark_conrod'], 'MarkCrankShaft' => $r['mark_crank_shaft'], 'MarkFOPump' => $r['mark_fo_pump'],
            'Checker' => $r['checker_name'],
        ];
    }
    return $out;
}

function export_painting(PDO $pdo, int $dept): array
{
    $stmt = $pdo->prepare(
        'SELECT h.tanggal, h.jam, c.name AS cond_name, sh.name AS shift_name, ck.name AS checker_name,
                ci.checking_item, ci.metode_pengecekan, ci.standard_min, ci.standard_max, ci.satuan, ci.tank_tube, ci.sort_order AS so,
                d.actual_result, d.category
         FROM t_checksheet_header h
         JOIN t_checksheet_detail d ON d.header_id = h.id
         JOIN m_checklist_item ci ON ci.id = d.checklist_item_id
         JOIN m_condition c ON c.id = h.condition_id
         LEFT JOIN m_shift sh ON sh.id = h.shift_id
         LEFT JOIN m_user ck ON ck.id = h.checker_id
         WHERE h.department_id = ? ORDER BY h.tanggal, h.id, ci.sort_order'
    );
    $stmt->execute([$dept]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'Condition' => $r['cond_name'], 'Date' => $r['tanggal'], 'Checking Item' => $r['checking_item'],
            'Metode Pengecekkan' => $r['metode_pengecekan'], 'Standard Min.' => $r['standard_min'], 'Standard Max.' => $r['standard_max'],
            'Satuan' => $r['satuan'], 'Shift' => $r['shift_name'], 'Jam' => $r['jam'] ? substr($r['jam'], 0, 5) : '',
            'Tank/Tube' => $r['tank_tube'], 'Actual Result' => $r['actual_result'], 'Category' => $r['category'],
            'Checked By :' => $r['checker_name'],
        ];
    }
    return $out;
}
