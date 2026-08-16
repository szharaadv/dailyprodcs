<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name, c.name AS condition_name, ck.name AS checker_name, s.name AS shift_name
     FROM t_checksheet_header h
     JOIN m_department d ON d.id = h.department_id
     JOIN m_condition c ON c.id = h.condition_id
     JOIN m_user ck ON ck.id = h.checker_id
     JOIN m_shift s ON s.id = h.shift_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_checksheets.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT det.actual_result, det.category, mi.checking_item, mi.metode_pengecekan,
            mi.standard_min, mi.standard_max, mi.tank_tube, mi.satuan, mi.sort_order
     FROM t_checksheet_detail det
     JOIN m_checklist_item mi ON mi.id = det.checklist_item_id
     WHERE det.header_id = ?
     ORDER BY mi.sort_order, det.id'
);
$stmt->execute([$id]);
$details = $stmt->fetchAll();

$backHref = 'view_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'painting_list.php';
$page_title = 'Checksheet Detail';
$page_subtitle = $header['department_name'] . ' · ' . $header['condition_name'] . ' · ' . date('d/m/Y', strtotime($header['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="<?= htmlspecialchars($backHref) ?>" class="dept-switch-link">&larr; Back to list</a>
    </div>

    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($header['tanggal']))) ?></div>
        </div>
        <div class="field-block">
            <label>Department</label>
            <div class="static-value"><?= htmlspecialchars($header['department_name']) ?></div>
        </div>
        <div class="field-block">
            <label>Condition</label>
            <div class="static-value"><?= htmlspecialchars($header['condition_name']) ?></div>
        </div>
        <div class="field-block">
            <label>Checked by</label>
            <div class="static-value"><?= htmlspecialchars($header['checker_name']) ?></div>
        </div>
        <div class="field-block">
            <label>Time</label>
            <div class="static-value"><?= htmlspecialchars(substr($header['jam'], 0, 5)) ?></div>
        </div>
        <div class="field-block">
            <label>Shift</label>
            <div class="static-value"><?= htmlspecialchars($header['shift_name']) ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="checklist-table">
            <thead>
                <tr>
                    <th>Condition</th>
                    <th>Checking Item</th>
                    <th>Checking Method</th>
                    <th>Standard Min.</th>
                    <th>Standard Max.</th>
                    <th>Tank/Tube</th>
                    <th>Unit</th>
                    <th>Actual Result</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($header['condition_name']) ?></td>
                    <td><?= htmlspecialchars($d['checking_item']) ?></td>
                    <td><?= htmlspecialchars($d['metode_pengecekan']) ?></td>
                    <td><?= htmlspecialchars($d['standard_min'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['standard_max'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['tank_tube'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['satuan'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['actual_result'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($d['category'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$details): ?><tr><td colspan="9" class="empty">No data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
