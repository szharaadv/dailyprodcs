<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name, op.name AS operator_name, fo.name AS foreman_name, sup.name AS supervisor_name
     FROM t_fopump_header h
     JOIN m_department d ON d.id = h.department_id
     LEFT JOIN m_user op ON op.id = h.operator_id
     LEFT JOIN m_user fo ON fo.id = h.foreman_id
     LEFT JOIN m_user sup ON sup.id = h.supervisor_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_fopump_checksheets.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM t_fopump_line WHERE header_id = ? ORDER BY line_no');
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

$totals = ['production' => 0, 'assembly' => 0, 'export' => 0];
foreach ($lines as $l) {
    $totals['production'] += (int)($l['production_qty'] ?? 0);
    $totals['assembly'] += (int)($l['assembly_qty'] ?? 0);
    $totals['export'] += (int)($l['export_qty'] ?? 0);
}

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(l.production_qty),0) AS prod, COALESCE(SUM(l.assembly_qty),0) AS assy, COALESCE(SUM(l.export_qty),0) AS exp
     FROM t_fopump_header h JOIN t_fopump_line l ON l.header_id = h.id
     WHERE h.department_id = ? AND YEAR(h.tanggal)=YEAR(?) AND MONTH(h.tanggal)=MONTH(?) AND h.tanggal < ?"
);
$stmt->execute([$header['department_id'], $header['tanggal'], $header['tanggal'], $header['tanggal']]);
$prior = $stmt->fetch();
$accum = [
    'production' => (int)$prior['prod'] + $totals['production'],
    'assembly' => (int)$prior['assy'] + $totals['assembly'],
    'export' => (int)$prior['exp'] + $totals['export'],
];

$backHref = 'view_fopump_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'fopump_list.php';
$page_title = 'FO Pump Report Detail';
$page_subtitle = $header['department_name'] . ' · ' . date('d/m/Y', strtotime($header['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="<?= htmlspecialchars($backHref) ?>" class="dept-switch-link">&larr; Back to list</a>
        <a href="fopump_list.php?department_id=<?= $header['department_id'] ?>&tanggal=<?= $header['tanggal'] ?>" class="dept-switch-link dept-switch-link-next">Edit this day &rarr;</a>
    </div>

    <div class="form-grid-top">
        <div class="field-block"><label>Date</label><div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($header['tanggal']))) ?></div></div>
        <div class="field-block"><label>Employee</label><div class="static-value"><?= htmlspecialchars($header['employee_count'] ?? '-') ?></div></div>
        <div class="field-block"><label>Working Time</label><div class="static-value"><?= htmlspecialchars($header['working_minutes'] !== null ? $header['working_minutes'] . ' min' : '-') ?></div></div>
        <div class="field-block"><label>Shift</label><div class="static-value"><?= htmlspecialchars($header['shift_label'] ?: '-') ?></div></div>
        <div class="field-block"><label>Operator</label><div class="static-value"><?= htmlspecialchars($header['operator_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Foreman</label><div class="static-value"><?= htmlspecialchars($header['foreman_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Supervisor</label><div class="static-value"><?= htmlspecialchars($header['supervisor_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Status</label><div class="static-value"><?= htmlspecialchars(ucfirst($header['status'])) ?></div></div>
    </div>

    <div class="table-wrap">
        <table class="fopump-table">
            <colgroup>
                <col class="fopump-col-no">
                <col class="fopump-col-model"><col class="fopump-col-qty">
                <col class="fopump-col-model"><col class="fopump-col-qty">
                <col class="fopump-col-model"><col class="fopump-col-qty">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">NO</th>
                    <th colspan="2">FO Pump Production</th>
                    <th colspan="2">To Assembly Line</th>
                    <th colspan="2">To Sparepart PTC</th>
                </tr>
                <tr><th>Model</th><th>Quantity</th><th>Model</th><th>Quantity</th><th>Model</th><th>Quantity</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="fopump-no"><?= (int)$l['line_no'] ?></td>
                    <td><?= htmlspecialchars($l['production_model'] ?: '') ?></td>
                    <td><?= htmlspecialchars($l['production_qty'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['assembly_model'] ?: '') ?></td>
                    <td><?= htmlspecialchars($l['assembly_qty'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['export_model'] ?: '') ?></td>
                    <td><?= htmlspecialchars($l['export_qty'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$lines): ?><tr><td colspan="7" class="empty">No line items.</td></tr><?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fopump-total-row">
                    <td>Total</td><td></td><td class="fopump-total"><?= $totals['production'] ?></td>
                    <td></td><td class="fopump-total"><?= $totals['assembly'] ?></td>
                    <td></td><td class="fopump-total"><?= $totals['export'] ?></td>
                </tr>
                <tr class="fopump-convert-row">
                    <td>Convert</td><td></td><td><?= htmlspecialchars($header['convert_production'] ?? '-') ?></td>
                    <td></td><td><?= htmlspecialchars($header['convert_assembly'] ?? '-') ?></td>
                    <td></td><td><?= htmlspecialchars($header['convert_export'] ?? '-') ?></td>
                </tr>
                <tr class="fopump-accum-row">
                    <td>Acumulation</td><td></td><td class="fopump-accum"><?= $accum['production'] ?></td>
                    <td></td><td class="fopump-accum"><?= $accum['assembly'] ?></td>
                    <td></td><td class="fopump-accum"><?= $accum['export'] ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
