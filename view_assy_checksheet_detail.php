<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
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
    'SELECT det.actual_result, det.consumable_item, mi.checking_item, mi.standard, mi.standard_min, mi.standard_max, mi.sort_order
     FROM t_assy_detail det
     JOIN m_assy_checklist_item mi ON mi.id = det.checklist_item_id
     WHERE det.header_id = ?
     ORDER BY mi.sort_order, det.id'
);
$stmt->execute([$id]);
$details = $stmt->fetchAll();

$backHref = 'view_assy_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');

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
        <table class="assy-table">
            <thead>
                <tr>
                    <th>Checking Item</th>
                    <th>Standard</th>
                    <th>Standard Min.</th>
                    <th>Standard Max.</th>
                    <th>Actual Result</th>
                    <th>Consumable Item</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['checking_item']) ?></td>
                    <td><?= htmlspecialchars($d['standard'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($d['standard_min'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['standard_max'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['actual_result'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($d['consumable_item'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$details): ?><tr><td colspan="6" class="empty">No data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
