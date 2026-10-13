<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/standard_verdict.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name, m.name AS model_name, ck.name AS checker_name
     FROM t_assy_header h
     JOIN m_department d ON d.id = h.department_id
     JOIN m_assy_model m ON m.id = h.model_id
     JOIN m_user ck ON ck.id = h.checker_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_assy_checksheets.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT mi.id AS item_id, det.actual_result, det.consumable_item, mi.checking_item, mi.standard, mi.standard_min, mi.standard_max, mi.sort_order
     FROM t_assy_detail det
     JOIN m_assy_checklist_item mi ON mi.id = det.checklist_item_id
     WHERE det.header_id = ?
     ORDER BY mi.sort_order, det.id'
);
$stmt->execute([$id]);
$details = $stmt->fetchAll();

// Which items on this engine have been reworked (Engine Revision), with the
// latest old->new for a hover tooltip on the "Revised" badge.
$revById = [];
$rev = $pdo->prepare(
    'SELECT checklist_item_id, old_value, new_value, revised_by_name, revised_at
     FROM t_assy_revision WHERE header_id = ? ORDER BY revised_at ASC, id ASC'
);
$rev->execute([$id]);
foreach ($rev as $r) {
    // keep the earliest old value but the latest new value / meta
    $iid = (int)$r['checklist_item_id'];
    if (!isset($revById[$iid])) $revById[$iid] = ['old' => $r['old_value']];
    $revById[$iid]['new'] = $r['new_value'];
    $revById[$iid]['by'] = $r['revised_by_name'];
    $revById[$iid]['at'] = $r['revised_at'];
}

$backHref = 'view_assy_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');
$backQ = isset($_GET['back']) && $_GET['back'] !== '' ? '&back=' . urlencode($_GET['back']) : '';

// Prev/next submitted record in chronological order (same department). Torque
// can have several records per day, so order by (date, id).
$dep = $header['department_id']; $tg = $header['tanggal']; $hid = (int)$header['id'];
$navStmt = $pdo->prepare("SELECT id FROM t_assy_header WHERE department_id=? AND status='submitted' AND (tanggal < ? OR (tanggal = ? AND id < ?)) ORDER BY tanggal DESC, id DESC LIMIT 1");
$navStmt->execute([$dep, $tg, $tg, $hid]);
$prevId = $navStmt->fetchColumn() ?: null;
$navStmt = $pdo->prepare("SELECT id FROM t_assy_header WHERE department_id=? AND status='submitted' AND (tanggal > ? OR (tanggal = ? AND id > ?)) ORDER BY tanggal ASC, id ASC LIMIT 1");
$navStmt->execute([$dep, $tg, $tg, $hid]);
$nextId = $navStmt->fetchColumn() ?: null;

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'assembly_list.php';
$page_title = 'Checksheet Detail';
$page_subtitle = $header['department_name'] . ' · ' . $header['model_name'] . ' · ' . date('d/m/Y', strtotime($header['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="<?= htmlspecialchars($backHref) ?>" class="dept-switch-link">&larr; Back to list</a>
        <span class="detail-daynav dept-switch-link-next">
            <?php if ($prevId): ?><a class="dept-switch-link" href="view_assy_checksheet_detail.php?id=<?= $prevId ?><?= $backQ ?>">&larr; Prev</a><?php else: ?><span class="dept-switch-link dept-switch-link-disabled">&larr; Prev</span><?php endif; ?>
            <?php if ($nextId): ?><a class="dept-switch-link" href="view_assy_checksheet_detail.php?id=<?= $nextId ?><?= $backQ ?>">Next &rarr;</a><?php else: ?><span class="dept-switch-link dept-switch-link-disabled">Next &rarr;</span><?php endif; ?>
        </span>
    </div>

    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($header['tanggal']))) ?></div>
        </div>
        <div class="field-block">
            <label>Model</label>
            <div class="static-value"><?= htmlspecialchars($header['model_name']) ?></div>
        </div>
        <div class="field-block">
            <label>Checker</label>
            <div class="static-value"><?= htmlspecialchars($header['checker_name']) ?></div>
        </div>
        <div class="field-block">
            <label>Mark Crank Shaft</label>
            <div class="static-value"><?= htmlspecialchars($header['mark_crank_shaft'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Mark Con-rod</label>
            <div class="static-value"><?= htmlspecialchars($header['mark_conrod'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Mark FO Pump</label>
            <div class="static-value"><?= htmlspecialchars($header['mark_fo_pump'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>No Cyl Block</label>
            <div class="static-value"><?= htmlspecialchars($header['no_cyl_block'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>No Engine</label>
            <div class="static-value"><?= htmlspecialchars($header['no_engine'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Detail Model</label>
            <div class="static-value"><?= htmlspecialchars($header['detail_model'] ?: '-') ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="assy-table assy-table-detail">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Checking Item</th>
                    <th>Standard</th>
                    <th>Standard Min.</th>
                    <th>Standard Max.</th>
                    <th>Actual Result</th>
                    <th>Status</th>
                    <th>Consumable Item</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNo = 0; foreach ($details as $d): $rowNo++;
                    $verdict = std_verdict($d['standard_min'], $d['standard_max'], $d['actual_result']);
                    $isNg = $verdict === 'NG';
                    $rv = $revById[(int)$d['item_id']] ?? null;
                    $rvTitle = $rv ? ('Revisi: ' . ($rv['old'] ?? '-') . ' → ' . ($rv['new'] ?? '-')
                        . ($rv['by'] ? ' · ' . $rv['by'] : '')
                        . ($rv['at'] ? ' · ' . date('d/m/Y H:i', strtotime($rv['at'])) : '')) : '';
                ?>
                <tr class="<?= $isNg ? 'row-ng' : '' ?>">
                    <td><?= $rowNo ?></td>
                    <td><?= htmlspecialchars($d['checking_item']) ?></td>
                    <td><?= htmlspecialchars($d['standard'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($d['standard_min'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['standard_max'] ?? '-') ?></td>
                    <td class="<?= $isNg ? 'val-ng' : ($verdict === 'OK' ? 'val-ok' : '') ?>"><?= htmlspecialchars($d['actual_result'] ?: '-') ?></td>
                    <td>
                        <?= $verdict === null ? '<span class="badge">-</span>' : ($isNg ? '<span class="badge badge-off">NG</span>' : '<span class="badge badge-ok">OK</span>') ?>
                        <?php if ($rv): ?><span class="badge badge-revised" title="<?= htmlspecialchars($rvTitle) ?>">&#8635; Revised</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($d['consumable_item'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$details): ?><tr><td colspan="8" class="empty">No data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.assy-table tr.row-ng { background: #fdf0f0; }
.assy-table td.val-ng { color: #b3261e; font-weight: 700; }
.assy-table td.val-ok { color: #1e7d34; font-weight: 600; }
.badge-revised { background:#eef2ff; color:#3538cd; border:1px solid #c7d0fd; font-weight:600;
    margin-left:6px; white-space:nowrap; cursor:help; }
</style>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
