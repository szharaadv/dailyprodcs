<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name, m.name AS model_name, m.fop_code, m.standard_cc_sec, m.rpm AS model_rpm, m.master_test,
            ck.name AS checker_name, fo.name AS foreman_name, sup.name AS supervisor_name
     FROM t_fopump_test_header h
     JOIN m_department d ON d.id = h.department_id
     JOIN m_fopump_test_model m ON m.id = h.model_id
     JOIN m_user ck ON ck.id = h.checker_id
     LEFT JOIN m_user fo ON fo.id = h.foreman_id
     LEFT JOIN m_user sup ON sup.id = h.supervisor_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_fopump_test_checksheets.php');
    exit;
}

$stmt = $pdo->prepare('SELECT row_no, rpm, cc_sec, shim FROM t_fopump_test_row WHERE header_id = ? ORDER BY sort_order, id');
$stmt->execute([$id]);
$rows = $stmt->fetchAll();

$backHref = 'view_fopump_test_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'fopump_test_list.php';
$page_title = 'Checksheet Detail';
$page_subtitle = $header['department_name'] . ' · ' . $header['model_name'] . ' · ' . date('d/m/Y', strtotime($header['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="<?= htmlspecialchars($backHref) ?>" class="dept-switch-link">&larr; Back to list</a>
    </div>

    <div class="form-grid-top">
        <div class="field-block"><label>Date</label><div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($header['tanggal']))) ?></div></div>
        <div class="field-block"><label>Model</label><div class="static-value"><?= htmlspecialchars($header['model_name']) ?></div></div>
        <div class="field-block"><label>FOP Code</label><div class="static-value"><?= htmlspecialchars($header['fop_code'] ?: '-') ?></div></div>
        <div class="field-block"><label>Standard (cc/sec)</label><div class="static-value"><?= htmlspecialchars($header['standard_cc_sec'] ?: '-') ?></div></div>
        <div class="field-block"><label>Rpm</label><div class="static-value"><?= htmlspecialchars($header['model_rpm'] ?: '-') ?></div></div>
        <div class="field-block"><label>Master Test</label><div class="static-value"><?= htmlspecialchars($header['master_test'] ?: '-') ?></div></div>
        <div class="field-block"><label>Oil Pressure</label><div class="static-value"><?= htmlspecialchars($header['oil_pressure'] ?: '-') ?></div></div>
        <div class="field-block"><label>Oil Temp.</label><div class="static-value"><?= htmlspecialchars($header['oil_temp'] ?: '-') ?></div></div>
        <div class="field-block"><label>Room Temp.</label><div class="static-value"><?= htmlspecialchars($header['room_temp'] ?: '-') ?></div></div>
        <div class="field-block"><label>Start Test Time</label><div class="static-value"><?= htmlspecialchars($header['start_test_time'] ?: '-') ?></div></div>
        <div class="field-block"><label>Checker</label><div class="static-value"><?= htmlspecialchars($header['checker_name']) ?></div></div>
        <div class="field-block"><label>Foreman</label><div class="static-value"><?= htmlspecialchars($header['foreman_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Supervisor</label><div class="static-value"><?= htmlspecialchars($header['supervisor_name'] ?: '-') ?></div></div>
    </div>

    <div class="table-wrap">
        <table class="fopump-check-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Rpm</th>
                    <th>cc / sec</th>
                    <th>Shim</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['row_no'] ?></td>
                    <td><?= htmlspecialchars($r['rpm'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($r['cc_sec'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($r['shim'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="empty">No rows.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
