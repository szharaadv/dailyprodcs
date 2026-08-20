<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/edit_requests.php';
require_admin();
$pdo = get_db();

$typeLabels = [
    'painting' => 'Painting',
    'assy' => 'Torque (Assembling)',
    'fopump' => 'FO Pump Daily Report',
    'fopump_reject' => 'FO Pump Daily Reject',
    'jig' => 'Sub Assembly (Jig)',
    'bakeoven' => 'Bake Oven',
    'washing' => 'Washing Machine',
    'paint_viscosity' => 'Paint Viscosity',
    '3s3t' => 'Checksheet 3S-3T',
];
$typeRoutes = [
    'painting' => 'painting_list.php',
    'assy' => 'assembly_list.php',
    'fopump' => 'fopump_list.php',
    'fopump_reject' => 'fopump_reject_list.php',
    'jig' => 'sub_assembly_list.php',
    'bakeoven' => 'bakeoven_list.php',
    'washing' => 'washing_list.php',
    'paint_viscosity' => 'paint_viscosity_list.php',
    '3s3t' => '3s3t_list.php',
];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['approve', 'deny'], true)) {
    $id = (int)($_POST['id'] ?? 0);
    $note = trim((string)($_POST['admin_note'] ?? ''));

    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare(
            "UPDATE t_edit_request
             SET status = 'approved', admin_note = ?, unlock_expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR), resolved_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$note ?: null, EDIT_REQUEST_UNLOCK_HOURS, $id]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE t_edit_request SET status = 'denied', admin_note = ?, resolved_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$note ?: null, $id]);
    }
    header('Location: edit_requests.php?done=1');
    exit;
}

$rows = $pdo->query(
    "SELECT r.*, u.name AS requester_name
     FROM t_edit_request r
     LEFT JOIN m_user u ON u.id = r.requested_by
     ORDER BY (r.status = 'pending') DESC, r.created_at DESC"
)->fetchAll();

$base_url = '../';
$active_nav = 'mgmt-edit-requests';
$page_title = 'Edit Requests';
$page_subtitle = 'Management · Edit Requests';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if (isset($_GET['done'])): ?><div class="alert alert-ok">Request updated.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Requested</th>
            <th>By</th>
            <th>Checksheet</th>
            <th>Record</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars(date('d M Y H:i', strtotime($row['created_at']))) ?></td>
            <td><?= htmlspecialchars($row['requester_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($typeLabels[$row['checksheet_type']] ?? $row['checksheet_type']) ?></td>
            <td><?= htmlspecialchars($row['label'] ?? ('#' . $row['header_id'])) ?></td>
            <td><?= nl2br(htmlspecialchars($row['reason'])) ?></td>
            <td>
                <?php if ($row['status'] === 'pending'): ?>
                    <span class="badge badge-off">Pending</span>
                <?php elseif ($row['status'] === 'approved'): ?>
                    <?php $active = strtotime($row['unlock_expires_at']) > time(); ?>
                    <span class="badge badge-ok">Approved<?= $active ? ' &middot; unlocked until ' . htmlspecialchars(date('d M H:i', strtotime($row['unlock_expires_at']))) : ' &middot; expired' ?></span>
                <?php else: ?>
                    <span class="badge badge-off">Denied</span>
                <?php endif; ?>
                <?php if ($row['admin_note']): ?><div class="import-hint">Note: <?= htmlspecialchars($row['admin_note']) ?></div><?php endif; ?>
            </td>
            <td class="row-actions">
                <?php if ($row['status'] === 'pending'): ?>
                <form method="post" style="display:inline-flex;gap:8px;align-items:center;">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <input type="text" name="admin_note" placeholder="Note (optional)" style="width:140px;">
                    <button type="submit" name="action" value="approve" class="btn" style="padding:6px 12px;font-size:12px;">Accept</button>
                    <button type="submit" name="action" value="deny" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="return confirm('Deny this edit request?')">Deny</button>
                </form>
                <?php elseif ($row['status'] === 'approved' && strtotime($row['unlock_expires_at']) > time() && isset($typeRoutes[$row['checksheet_type']])): ?>
                    <a class="cs-view-btn-sm" href="../<?= $typeRoutes[$row['checksheet_type']] ?>?edit_id=<?= $row['header_id'] ?>">Edit Now &rarr;</a>
                <?php else: ?>
                    &mdash;
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No edit requests yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
