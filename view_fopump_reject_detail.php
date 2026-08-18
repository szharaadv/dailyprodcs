<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name
     FROM t_fopump_reject_header h
     JOIN m_department d ON d.id = h.department_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_fopump_reject_checksheets.php');
    exit;
}

$stmt = $pdo->prepare('SELECT line_no, model, quantity, remarks FROM t_fopump_reject_line WHERE header_id = ? ORDER BY line_no');
$stmt->execute([$id]);
$lines = $stmt->fetchAll();

$total = 0;
foreach ($lines as $l) { $total += (int)($l['quantity'] ?? 0); }

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$backHref = 'view_fopump_reject_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'fopump_reject_list.php';
$page_title = 'Checksheet Detail';
$page_subtitle = $header['department_name'] . ' · ' . $monthNames[$header['month']] . ' ' . $header['year'];
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="<?= htmlspecialchars($backHref) ?>" class="dept-switch-link">&larr; Back to list</a>
    </div>

    <div class="form-grid-top">
        <div class="field-block"><label>Month</label><div class="static-value"><?= htmlspecialchars($monthNames[$header['month']]) ?></div></div>
        <div class="field-block"><label>Year</label><div class="static-value"><?= (int)$header['year'] ?></div></div>
        <div class="field-block"><label>Target</label><div class="static-value"><?= $header['target'] !== null ? (int)$header['target'] : '-' ?></div></div>
        <div class="field-block"><label>Total Reject</label><div class="static-value"><?= $total ?></div></div>
    </div>

    <div class="table-wrap">
        <table class="fopump-check-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Model</th>
                    <th>Quantity</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= (int)$l['line_no'] ?></td>
                    <td><?= htmlspecialchars($l['model'] ?: '-') ?></td>
                    <td><?= $l['quantity'] !== null ? (int)$l['quantity'] : '-' ?></td>
                    <td><?= htmlspecialchars($l['remarks'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$lines): ?><tr><td colspan="4" class="empty">No rows.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
