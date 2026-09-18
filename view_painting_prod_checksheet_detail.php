<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name, ck.name AS checker_name, sh.name AS shift_name,
            fo.name AS foreman_name, sup.name AS supervisor_name
     FROM t_painting_prod_header h
     JOIN m_department d ON d.id = h.department_id
     LEFT JOIN m_user ck ON ck.id = h.checker_id
     LEFT JOIN m_shift sh ON sh.id = h.shift_id
     LEFT JOIN m_user fo ON fo.id = h.foreman_id
     LEFT JOIN m_user sup ON sup.id = h.supervisor_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_painting_prod_checksheets.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM t_painting_prod_line WHERE header_id = ? ORDER BY line_no');
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

$CATS = ['cb', 'fot', 'fw', 'part', 'others'];
$totals = array_fill_keys($CATS, 0);
foreach ($lines as $l) {
    foreach ($CATS as $c) $totals[$c] += (int)($l[$c] ?? 0);
}

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(l.cb),0) AS cb, COALESCE(SUM(l.fot),0) AS fot, COALESCE(SUM(l.fw),0) AS fw,
            COALESCE(SUM(l.part),0) AS part, COALESCE(SUM(l.others),0) AS others
     FROM t_painting_prod_header h JOIN t_painting_prod_line l ON l.header_id = h.id
     WHERE h.department_id = ? AND YEAR(h.tanggal)=YEAR(?) AND MONTH(h.tanggal)=MONTH(?) AND h.tanggal < ?"
);
$stmt->execute([$header['department_id'], $header['tanggal'], $header['tanggal'], $header['tanggal']]);
$prior = $stmt->fetch();
$accum = [];
foreach ($CATS as $c) $accum[$c] = (int)$prior[$c] + $totals[$c];

$hariNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$hari = $hariNames[(int)date('w', strtotime($header['tanggal']))];

$backHref = 'view_painting_prod_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'painting_prod_list.php';
$page_title = 'Painting Report Detail';
$page_subtitle = $header['department_name'] . ' · ' . date('d/m/Y', strtotime($header['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="<?= htmlspecialchars($backHref) ?>" class="dept-switch-link">&larr; Back to list</a>
        <a href="painting_prod_list.php?department_id=<?= $header['department_id'] ?>&tanggal=<?= $header['tanggal'] ?>" class="dept-switch-link dept-switch-link-next">Edit this day &rarr;</a>
    </div>

    <div class="form-grid-top">
        <div class="field-block"><label>Day</label><div class="static-value"><?= htmlspecialchars($hari) ?></div></div>
        <div class="field-block"><label>Date</label><div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($header['tanggal']))) ?></div></div>
        <div class="field-block"><label>Worker</label><div class="static-value"><?= htmlspecialchars($header['checker_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Shift</label><div class="static-value"><?= htmlspecialchars($header['shift_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Foreman</label><div class="static-value"><?= htmlspecialchars($header['foreman_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Supervisor</label><div class="static-value"><?= htmlspecialchars($header['supervisor_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Status</label><div class="static-value"><?= htmlspecialchars(ucfirst($header['status'])) ?></div></div>
    </div>

    <div class="table-wrap">
        <table class="fopump-table pprod-table">
            <colgroup>
                <col class="fopump-col-no">
                <col class="pprod-col-model">
                <col class="pprod-col-qty"><col class="pprod-col-qty"><col class="pprod-col-qty">
                <col class="pprod-col-qty"><col class="pprod-col-qty">
                <col class="pprod-col-note">
            </colgroup>
            <thead>
                <tr>
                    <th>NO</th><th>MODEL</th>
                    <th>CB</th><th>FOT</th><th>FW</th><th>PART</th><th>OTHERS</th>
                    <th>REMARKS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="fopump-no"><?= (int)$l['line_no'] ?></td>
                    <td><?= htmlspecialchars($l['model'] ?: '') ?></td>
                    <td><?= htmlspecialchars($l['cb'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['fot'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['fw'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['part'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['others'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['keterangan'] ?: '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$lines): ?><tr><td colspan="8" class="empty">No line items.</td></tr><?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="fopump-total-row">
                    <td colspan="2">TOTAL</td>
                    <?php foreach ($CATS as $c): ?><td class="fopump-total"><?= $totals[$c] ?></td><?php endforeach; ?>
                    <td></td>
                </tr>
                <?php // CB/FOT/FW/PART hold the same figure by design, so AKUMULASI shows one
                      // column's (CB) month-to-date running total, not the sum of the four. ?>
                <tr class="fopump-accum-row">
                    <td colspan="2">AKUMULASI</td>
                    <td class="fopump-accum" colspan="<?= count($CATS) - 1 ?>"><?= $accum['cb'] ?></td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
