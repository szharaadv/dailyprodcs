<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
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

// Each person only sees their own edit requests here — since every User
// now signs in as themselves (their own PIN, see login.php), there's no
// need to pick "who am I" anymore. Admin has the full queue at
// admin/edit_requests.php instead, so this page shows nothing useful for
// the Admin identity (it isn't tied to any one m_user row).
$me = current_user();
$sql = "SELECT r.*, u.name AS requester_name
        FROM t_edit_request r
        LEFT JOIN m_user u ON u.id = r.requested_by
        WHERE r.requested_by = ?
        ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$me['id'] ?? 0]);
$rows = $stmt->fetchAll();

$base_url = '';
$active_nav = 'my-edit-requests';
$page_title = 'Edit Requests';
$page_subtitle = 'Track your edit requests and their status';
require __DIR__ . '/includes/app_top.php';
?>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Requested</th>
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
            <td><?= htmlspecialchars($typeLabels[$row['checksheet_type']] ?? $row['checksheet_type']) ?></td>
            <td><?= htmlspecialchars($row['label'] ?? ('#' . $row['header_id'])) ?></td>
            <td><?= nl2br(htmlspecialchars($row['reason'])) ?></td>
            <td>
                <?php if ($row['status'] === 'pending'): ?>
                    <span class="badge badge-off">Pending</span>
                <?php elseif ($row['status'] === 'approved'): ?>
                    <?php $active = strtotime($row['unlock_expires_at']) > time(); ?>
                    <span class="badge badge-ok">Approved</span>
                    <div class="import-hint"><?= $active ? 'Editable until ' . htmlspecialchars(date('d M Y H:i', strtotime($row['unlock_expires_at']))) : 'Unlock window expired' ?></div>
                <?php else: ?>
                    <span class="badge badge-off">Denied</span>
                <?php endif; ?>
                <?php if ($row['admin_note']): ?><div class="import-hint">Admin note: <?= htmlspecialchars($row['admin_note']) ?></div><?php endif; ?>
            </td>
            <td>
                <?php if ($row['status'] === 'approved' && strtotime($row['unlock_expires_at']) > time() && isset($typeRoutes[$row['checksheet_type']])): ?>
                    <a class="cs-view-btn-sm" href="<?= $typeRoutes[$row['checksheet_type']] ?>?edit_id=<?= $row['header_id'] ?>">Edit Now &rarr;</a>
                <?php else: ?>
                    &mdash;
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">You haven't submitted any edit requests yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
