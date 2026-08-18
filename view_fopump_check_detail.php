<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name, m.name AS model_name, m.fop_code, m.part_no,
            ck.name AS checker_name, fo.name AS foreman_name, sup.name AS supervisor_name
     FROM t_fopump_check_header h
     JOIN m_department d ON d.id = h.department_id
     JOIN m_fopump_check_model m ON m.id = h.model_id
     JOIN m_user ck ON ck.id = h.checker_id
     LEFT JOIN m_user fo ON fo.id = h.foreman_id
     LEFT JOIN m_user sup ON sup.id = h.supervisor_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_fopump_check_checksheets.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, sample_no FROM t_fopump_check_sample WHERE header_id = ? ORDER BY sort_order, id');
$stmt->execute([$id]);
$samples = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, checking_item, standard FROM m_fopump_check_item WHERE model_id = ? ORDER BY sort_order, id');
$stmt->execute([$header['model_id']]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT checklist_item_id, sample_id, actual_result FROM t_fopump_check_detail WHERE header_id = ?');
$stmt->execute([$id]);
$values = [];
foreach ($stmt->fetchAll() as $d) {
    $values[$d['checklist_item_id']][$d['sample_id']] = $d['actual_result'];
}

$backHref = 'view_fopump_check_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'fopump_check_list.php';
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
        <div class="field-block"><label>Part No.</label><div class="static-value"><?= htmlspecialchars($header['part_no'] ?: '-') ?></div></div>
        <div class="field-block"><label>Prod. Date Code</label><div class="static-value"><?= htmlspecialchars($header['prod_date_code'] ?: '-') ?></div></div>
        <div class="field-block"><label>Checker</label><div class="static-value"><?= htmlspecialchars($header['checker_name']) ?></div></div>
        <div class="field-block"><label>Foreman</label><div class="static-value"><?= htmlspecialchars($header['foreman_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Supervisor</label><div class="static-value"><?= htmlspecialchars($header['supervisor_name'] ?: '-') ?></div></div>
    </div>

    <div class="table-wrap">
        <table class="fopump-check-table">
            <thead>
                <tr>
                    <th class="fopump-check-item-col">Checking Item</th>
                    <th class="fopump-check-std-col">Standard</th>
                    <?php foreach ($samples as $s): ?>
                        <th class="fopump-check-sample-col">No. <?= htmlspecialchars($s['sample_no']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="fopump-check-item-cell"><?= htmlspecialchars($item['checking_item']) ?></td>
                    <td><?= htmlspecialchars($item['standard'] ?: '-') ?></td>
                    <?php foreach ($samples as $s): ?>
                        <td><?= htmlspecialchars($values[$item['id']][$s['id']] ?? '-') ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?><tr><td colspan="<?= 2 + count($samples) ?>" class="empty">No checklist items.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
