<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM t_fopump_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([(int)$_GET['id']]);
    header('Location: my_fopump_drafts.php?deleted=1');
    exit;
}

$sql = "SELECT h.*, d.name AS department_name,
               (SELECT COUNT(*) FROM t_fopump_line l WHERE l.header_id = h.id) AS line_count
        FROM t_fopump_header h
        JOIN m_department d ON d.id = h.department_id
        WHERE h.status = 'draft'
        ORDER BY h.created_at DESC";
$drafts = $pdo->query($sql)->fetchAll();

$base_url = '';
$active_nav = 'my-drafts';
$section_route = 'fopump_list.php';
$page_title = 'My Drafts';
$page_subtitle = 'FO Pump daily reports not yet submitted';
require __DIR__ . '/includes/app_top.php';
?>

<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Draft deleted.</div><?php endif; ?>

<div class="cs-card-list">
    <?php foreach ($drafts as $row): ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= htmlspecialchars(date('d', strtotime($row['tanggal']))) ?></div>
            <div class="cs-card-month"><?= htmlspecialchars(date('M', strtotime($row['tanggal']))) ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title"><?= htmlspecialchars($row['department_name']) ?> &middot; FO Pump</div>
            <div class="cs-card-meta"><?= (int)$row['line_count'] ?> line item(s) &middot; last saved <?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></div>
        </div>
        <span class="cs-status cs-status-draft">Draft</span>
        <div class="cs-card-actions">
            <a href="fopump_list.php?department_id=<?= $row['department_id'] ?>&draft_id=<?= $row['id'] ?>" class="cs-view-btn">Continue</a>
            <a href="my_fopump_drafts.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this draft?')" class="cs-delete-link">Delete</a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$drafts): ?><div class="empty-state">No drafts yet.</div><?php endif; ?>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
