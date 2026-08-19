<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$year = (int)($_GET['year'] ?? date('Y'));

$stmt = $pdo->prepare(
    "SELECT h.*,
        (SELECT COALESCE(SUM(quantity),0) FROM t_fopump_reject_line WHERE header_id = h.id) AS total_reject
     FROM t_fopump_reject_header h
     WHERE h.department_id = ? AND h.year = ? AND h.status = 'submitted'
     ORDER BY h.month"
);
$stmt->execute([$department_id, $year]);
$results = $stmt->fetchAll();

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$backQuery = $_SERVER['QUERY_STRING'] ?? '';

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'fopump_reject_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view FO Pump monthly reject logs';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Year</label>
            <select name="year">
                <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</form>

<div class="cs-card-list">
    <?php foreach ($results as $row): ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= htmlspecialchars($monthNames[$row['month']]) ?></div>
            <div class="cs-card-month"><?= (int)$row['year'] ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title">Total Reject <?= (int)$row['total_reject'] ?><?= $row['target'] !== null ? ' &middot; Target ' . (int)$row['target'] : '' ?></div>
        </div>
        <span class="cs-status cs-status-submitted">Submitted</span>
        <a href="view_fopump_reject_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($backQuery) ?>" class="cs-view-btn">View &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No reject logs found for this year.</div><?php endif; ?>
</div>

<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
